<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Core\AssignCrewFlightRequest;
use App\Http\Requests\Core\StoreCrewMemberRequest;
use App\Http\Requests\Core\UpdateCrewMemberRequest;
use App\Services\Core\CrewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CrewController extends BaseApiController
{
    public function calendar(string $id): JsonResponse
    {
        $this->crewService->getCrewMemberById($id);
        return $this->successResponse(\Illuminate\Support\Facades\DB::table('crew_availability_windows')->where('crew_id',$id)->orderByDesc('id')->paginate(20),'Lịch khả dụng');
    }
    public function addCalendar(Request $r,string $id): JsonResponse
    {
        return $this->successResponse(app(\App\Services\Core\CrewCalendar::class)->add($id,$r->all()),'Đã ghi lịch khả dụng',201);
    }
    public function cancelCalendar(Request $r,string $id,int $window): JsonResponse
    {
        app(\App\Services\Core\CrewCalendar::class)->cancel($id,$window,$r->all());return $this->successResponse(null,'Đã hủy khoảng khả dụng');
    }
    public function suggestions(Request $r,string $flightId): JsonResponse
    {
        $data=$r->validate(['position'=>'required|in:CAPTAIN,FIRST_OFFICER,PURSER,FLIGHT_ATTENDANT','replace_id'=>'nullable|uuid']);
        return $this->successResponse($this->crewService->suggestions($flightId,$data['position'],$data['replace_id']??null),'Đề xuất theo quy tắc; chưa tự phân công');
    }
    public function __construct(
        protected CrewService $crewService
    ) {}

    /**
     * Danh sách nhân sự tổ bay (UC-15)
     */
    public function index(Request $request): JsonResponse
    {
        $crew = $this->crewService->getAllCrewMembers($request->all());
        return $this->successResponse($crew, 'Lấy danh sách tổ bay thành công');
    }

    /**
     * Thêm mới nhân sự tổ bay (UC-15)
     */
    public function store(StoreCrewMemberRequest $request): JsonResponse
    {
        $member = $this->crewService->createCrewMember($request->validated());
        return $this->successResponse($member, 'Thêm mới nhân sự tổ bay thành công', Response::HTTP_CREATED);
    }

    /**
     * Chi tiết nhân sự tổ bay
     */
    public function show(string $id): JsonResponse
    {
        $member = $this->crewService->getCrewMemberById($id);
        return $this->successResponse($member, 'Lấy chi tiết nhân sự tổ bay thành công');
    }

    /**
     * Cập nhật hồ sơ nhân sự tổ bay
     */
    public function update(UpdateCrewMemberRequest $request, string $id): JsonResponse
    {
        $member = $this->crewService->updateCrewMember($id, $request->validated());
        return $this->successResponse($member, 'Cập nhật hồ sơ tổ bay thành công');
    }

    /**
     * Xóa hồ sơ nhân sự tổ bay
     */
    public function destroy(string $id): JsonResponse
    {
        $this->crewService->deleteCrewMember($id);
        return $this->successResponse(null, 'Xóa hồ sơ nhân sự thành công');
    }

    /**
     * Danh sách phân công tổ bay cho một chuyến bay (UC-16)
     */
    public function getFlightAssignments(string $flightId): JsonResponse
    {
        $assignments = $this->crewService->getFlightCrewAssignments($flightId);
        $validation  = $this->crewService->validateMinimumFlightCrew($flightId);

        return $this->successResponse([
            'assignments' => $assignments,
            'compliance'  => $validation,
        ], 'Lấy danh sách phân công tổ bay thành công');
    }

    /**
     * Phân công nhân sự vào chuyến bay (UC-16, UC-17)
     */
    public function assignCrew(AssignCrewFlightRequest $request, string $flightId): JsonResponse
    {
        $assignment = $this->crewService->assignCrewToFlight($flightId, $request->validated());
        return $this->successResponse($assignment, 'Phân công tổ bay thành công', Response::HTTP_CREATED);
    }

    /**
     * Hủy phân công nhân sự khỏi chuyến bay
     */
    public function removeAssignment(string $assignmentId): JsonResponse
    {
        $this->crewService->removeCrewAssignment($assignmentId);
        return $this->successResponse(null, 'Hủy phân công tổ bay thành công');
    }
}
