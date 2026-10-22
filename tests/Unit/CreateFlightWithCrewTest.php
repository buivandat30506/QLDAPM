<?php

namespace Tests\Unit;

use App\Http\Controllers\Web\FlightOperationsController;
use App\Http\Requests\Core\CreateFlightWithCrewRequest;
use App\Models\FlightCrewAssignment;
use App\Models\OperationalFlight;
use App\Models\User;
use App\Services\Core\CrewService;
use App\Services\Core\FlightManagementService;
use App\Services\Core\FlightOperations;
use App\Services\Core\ScheduleService;
use App\Shared\Security\AccessControl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\PresenceVerifierInterface;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class CreateFlightWithCrewTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const FLIGHT = '11111111-1111-4111-8111-111111111111';
    private const CAPTAIN = '22222222-2222-4222-8222-222222222222';
    private const OFFICER = '33333333-3333-4333-8333-333333333333';
    private const ATTENDANT = '44444444-4444-4444-8444-444444444444';
    private Container $container;
    private Container $previousContainer;
    private $previousFacadeApplication;
    private Manager $database;
    private Factory $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->container = new Container;
        Container::setInstance($this->container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->container);

        // This test never boots the application or connects to the project database.
        $this->database = new Manager($this->container);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->container->instance('db', $this->database->getDatabaseManager());
        $this->database->getConnection()->statement('CREATE TABLE test_flights (id TEXT PRIMARY KEY)');
        $this->database->getConnection()->statement('CREATE TABLE test_assignments (flight_id TEXT, crew_id TEXT, position TEXT)');

        $this->validator = new Factory(new Translator(new ArrayLoader, 'en'), $this->container);
        $presence = Mockery::mock(PresenceVerifierInterface::class);
        $presence->shouldReceive('getCount')->andReturnUsing(fn ($table, $column, $value) => in_array($value, [
            '55555555-5555-4555-8555-555555555555', self::CAPTAIN, self::OFFICER, self::ATTENDANT,
            '66666666-6666-4666-8666-666666666666',
        ], true) ? 1 : 0);
        $this->validator->setPresenceVerifier($presence);
        $this->container->instance('validator', $this->validator);
    }

    protected function tearDown(): void
    {
        $this->database->getConnection()->disconnect();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function flightData(): array
    {
        return ['schedule_id' => '55555555-5555-4555-8555-555555555555', 'service_date' => '2026-11-15'];
    }

    private function request(array $crew = []): CreateFlightWithCrewRequest
    {
        $request = Mockery::mock(CreateFlightWithCrewRequest::class);
        $request->shouldReceive('validated')->once()->andReturn($this->flightData() + ($crew ? ['crew' => $crew] : []));

        return $request;
    }

    private function creationService(): void
    {
        $service = Mockery::mock(FlightManagementService::class);
        $service->shouldReceive('createOperationalFlight')->once()->with($this->flightData())->andReturnUsing(function () {
            $this->database->getConnection()->table('test_flights')->insert(['id' => self::FLIGHT]);
            $flight = new OperationalFlight;
            $flight->setRawAttributes(['id' => self::FLIGHT]);

            return $flight;
        });
        $this->container->instance(FlightManagementService::class, $service);
    }

    private function redirectResponse(string $message): RedirectResponse
    {
        $response = Mockery::mock(RedirectResponse::class);
        $response->shouldReceive('with')->once()->with('success', $message)->andReturnSelf();
        $redirector = Mockery::mock(Redirector::class);
        $redirector->shouldReceive('route')->once()->with('admin.flights.show', self::FLIGHT)->andReturn($response);
        $this->container->instance('redirect', $redirector);

        return $response;
    }

    public function test_creation_saves_selected_positions_and_multiple_attendants_in_one_transaction(): void
    {
        $this->creationService();
        $crew = Mockery::mock(CrewService::class);
        $crew->shouldReceive('assignCrewToFlight')->times(4)->andReturnUsing(function ($flightId, $data) {
            $this->assertSame(self::FLIGHT, $flightId);
            $this->assertSame(1, $this->database->getConnection()->transactionLevel());
            $this->database->getConnection()->table('test_assignments')->insert([
                'flight_id' => $flightId, 'crew_id' => $data['crew_id'], 'position' => $data['assigned_position'],
            ]);

            return new FlightCrewAssignment;
        });
        $this->container->instance(CrewService::class, $crew);
        $response = $this->redirectResponse('Đã tạo chuyến và lưu phân công phi hành đoàn. Phân công cần được duyệt và xác nhận.');
        $result = (new FlightOperationsController)->store($this->request([
            'CAPTAIN' => [self::CAPTAIN], 'FIRST_OFFICER' => [self::OFFICER],
            'PURSER' => [null], 'FLIGHT_ATTENDANT' => [self::ATTENDANT, '66666666-6666-4666-8666-666666666666'],
        ]));

        $this->assertSame($response, $result);
        $this->assertSame(1, $this->database->getConnection()->table('test_flights')->count());
        $this->assertSame(['CAPTAIN', 'FIRST_OFFICER', 'FLIGHT_ATTENDANT', 'FLIGHT_ATTENDANT'],
            $this->database->getConnection()->table('test_assignments')->pluck('position')->all());
    }

    public function test_invalid_assignment_rolls_back_flight_and_previous_assignments_and_identifies_the_field(): void
    {
        $this->creationService();
        $crew = Mockery::mock(CrewService::class);
        $crew->shouldReceive('assignCrewToFlight')->with(self::FLIGHT, ['crew_id' => self::CAPTAIN, 'assigned_position' => 'CAPTAIN'])
            ->once()->andReturnUsing(function () {
                $this->database->getConnection()->table('test_assignments')->insert(['flight_id' => self::FLIGHT, 'crew_id' => self::CAPTAIN, 'position' => 'CAPTAIN']);

                return new FlightCrewAssignment;
            });
        $crew->shouldReceive('assignCrewToFlight')->with(self::FLIGHT, ['crew_id' => self::OFFICER, 'assigned_position' => 'FIRST_OFFICER'])
            ->once()->andThrow(ValidationException::withMessages(['rest' => ['Trùng ca trực.']]));
        $this->container->instance(CrewService::class, $crew);

        try {
            (new FlightOperationsController)->store($this->request(['CAPTAIN' => [self::CAPTAIN], 'FIRST_OFFICER' => [self::OFFICER]]));
            $this->fail('Invalid crew must abort flight creation.');
        } catch (ValidationException $error) {
            $this->assertSame(['crew.FIRST_OFFICER.0' => ['Trùng ca trực.']], $error->errors());
        }
        $this->assertSame(0, $this->database->getConnection()->table('test_flights')->count());
        $this->assertSame(0, $this->database->getConnection()->table('test_assignments')->count());
    }

    public function test_missing_assignment_permission_rolls_back_flight_creation(): void
    {
        $this->creationService();
        $crew = Mockery::mock(CrewService::class);
        $crew->shouldReceive('assignCrewToFlight')->once()->andThrow(new AuthorizationException('Denied'));
        $this->container->instance(CrewService::class, $crew);

        try {
            (new FlightOperationsController)->store($this->request(['CAPTAIN' => [self::CAPTAIN]]));
            $this->fail('Unauthorized assignment must abort flight creation.');
        } catch (AuthorizationException $error) {
            $this->assertSame('Denied', $error->getMessage());
        }
        $this->assertSame(0, $this->database->getConnection()->table('test_flights')->count());
    }

    public function test_creation_without_crew_keeps_existing_behavior(): void
    {
        $this->creationService();
        $crew = Mockery::mock(CrewService::class);
        $crew->shouldNotReceive('assignCrewToFlight');
        $this->container->instance(CrewService::class, $crew);
        $response = $this->redirectResponse('Đã tạo chuyến từ lịch công bố.');

        $this->assertSame($response, (new FlightOperationsController)->store($this->request()));
        $this->assertSame(1, $this->database->getConnection()->table('test_flights')->count());
    }

    public function test_request_rejects_duplicate_people_invalid_ids_unknown_roles_and_multiple_captains(): void
    {
        $request = new CreateFlightWithCrewRequest;
        foreach ([
            ['CAPTAIN' => [self::CAPTAIN], 'FIRST_OFFICER' => [self::CAPTAIN]],
            ['CAPTAIN' => ['invalid-id']],
            ['CAPTAIN' => ['99999999-9999-4999-8999-999999999999']],
            ['INVALID_ROLE' => [self::CAPTAIN]],
            ['CAPTAIN' => [self::CAPTAIN, self::OFFICER]],
            ['CAPTAIN' => self::CAPTAIN],
        ] as $crew) {
            $validator = $this->validator->make($this->flightData() + ['crew' => $crew], $request->rules(), $request->messages());
            $this->assertTrue($validator->fails(), json_encode($crew));
        }

        foreach ([[], ['CAPTAIN' => [null], 'FIRST_OFFICER' => [null], 'PURSER' => [null]],
            ['CAPTAIN' => [self::CAPTAIN], 'FIRST_OFFICER' => [self::OFFICER],
                'FLIGHT_ATTENDANT' => [self::ATTENDANT, '66666666-6666-4666-8666-666666666666']],
        ] as $crew) {
            $validator = $this->validator->make($this->flightData() + ['crew' => $crew], $request->rules(), $request->messages());
            $this->assertFalse($validator->fails(), $validator->errors()->toJson());
        }
    }

    private function renderIndexWithScopes(array $readScopes, array $writeScopes, bool $expectedPermission, array $expectedIds, array $expectedScheduleIds = []): void
    {
        $this->database->getConnection()->statement('CREATE TABLE crew_members (id TEXT, crew_code TEXT, full_name TEXT, rank TEXT, base_airport_code TEXT)');
        $this->database->getConnection()->table('crew_members')->insert([
            ['id' => self::CAPTAIN, 'crew_code' => 'CAP-HAN', 'full_name' => 'Captain HAN', 'rank' => 'CAPTAIN', 'base_airport_code' => 'HAN'],
            ['id' => self::OFFICER, 'crew_code' => 'FO-SGN', 'full_name' => 'Officer SGN', 'rank' => 'FIRST_OFFICER', 'base_airport_code' => 'SGN'],
        ]);
        $user = new User;
        $request = Request::create('/admin/flights');
        $request->setUserResolver(fn () => $user);
        $access = Mockery::mock(AccessControl::class);
        $access->shouldReceive('scopes')->with($user, 'crew.read')->andReturn(collect($readScopes)->map(fn ($s) => (object) $s));
        $access->shouldReceive('scopes')->with($user, 'crew.write')->andReturn(collect($writeScopes)->map(fn ($s) => (object) $s));
        $this->container->instance(AccessControl::class, $access);
        $gate = Mockery::mock(GateContract::class);
        $gate->shouldReceive('allows')->once()->with('flight.write')->andReturn(true);
        Gate::swap($gate);

        $crew = Mockery::mock(CrewService::class);
        if ($expectedPermission) {
            $crew->shouldReceive('query')->once()->with(['status' => 'AVAILABLE'])->andReturn($this->database->getConnection()->table('crew_members'));
        } else {
            $crew->shouldNotReceive('query');
        }
        $this->container->instance(CrewService::class, $crew);
        $flights = Mockery::mock();
        $flights->shouldReceive('paginate')->once()->with(20)->andReturnSelf();
        $flights->shouldReceive('withQueryString')->once()->andReturn(collect());
        $operations = Mockery::mock(FlightOperations::class);
        $operations->shouldReceive('query')->once()->with([])->andReturn($flights);
        $scheduleQuery = Mockery::mock();
        $scheduleQuery->shouldReceive('get')->once()->andReturn(collect([
            (object) ['id' => 'schedule-HAN', 'route' => (object) ['origin_airport_code' => 'HAN']],
            (object) ['id' => 'schedule-SGN', 'route' => (object) ['origin_airport_code' => 'SGN']],
        ]));
        $schedules = Mockery::mock(ScheduleService::class);
        $schedules->shouldReceive('query')->once()->with(['status' => 'PUBLISHED'])->andReturn($scheduleQuery);
        $this->container->instance(ScheduleService::class, $schedules);

        $view = Mockery::mock(View::class);
        $factory = Mockery::mock(ViewFactory::class);
        $factory->shouldReceive('make')->once()->with('operations.index', Mockery::on(function ($data) use ($expectedPermission, $expectedIds, $expectedScheduleIds) {
            $this->assertSame($expectedPermission, $data['canAssignCrewOnCreate']);
            $this->assertSame($expectedIds, $data['creationCrew']->pluck('id')->all());
            $this->assertSame($expectedScheduleIds, $data['crewAssignableScheduleIds']);

            return true;
        }), [])->andReturn($view);
        $this->container->instance(ViewFactory::class, $factory);
        $this->assertSame($view, (new FlightOperationsController)->index($request, $operations));
    }

    public function test_creation_lookup_only_includes_airports_with_assignment_permission(): void
    {
        $this->renderIndexWithScopes([['scope_type' => 'global', 'scope_id' => '*']],
            [['scope_type' => 'airport', 'scope_id' => 'HAN']], true, [self::CAPTAIN], ['schedule-HAN']);
    }

    public function test_creation_lookup_is_not_loaded_without_crew_read_permission(): void
    {
        $this->renderIndexWithScopes([], [['scope_type' => 'global', 'scope_id' => '*']], false, []);
    }

    public function test_global_assignment_permission_allows_both_departure_airports(): void
    {
        $this->renderIndexWithScopes([['scope_type' => 'global', 'scope_id' => '*']],
            [['scope_type' => 'global', 'scope_id' => '*']], true, [self::CAPTAIN, self::OFFICER], ['schedule-HAN', 'schedule-SGN']);
    }

    public function test_assignment_permission_for_another_airport_does_not_allow_any_schedule(): void
    {
        $this->renderIndexWithScopes([['scope_type' => 'global', 'scope_id' => '*']],
            [['scope_type' => 'airport', 'scope_id' => 'DAD']], true, [], []);
    }

    public function test_creation_lookup_is_not_loaded_without_crew_write_permission(): void
    {
        $this->renderIndexWithScopes([['scope_type' => 'global', 'scope_id' => '*']], [], false, []);
    }
}
