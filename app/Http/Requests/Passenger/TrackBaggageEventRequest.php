<?php

namespace App\Http\Requests\Passenger;

use App\Http\Requests\BaseApiRequest;

class TrackBaggageEventRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'tag_code'     => ['required', 'string', 'size:10', 'exists:baggage_tags,tag_code'],
            'event_type'   => ['required', 'string', 'in:CHECK_IN,LOADED_TO_AIRCRAFT,UNLOADED,CLAIMED'],
            'location'     => ['required', 'string', 'max:100'],
            'custody_unit' => ['required', 'string', 'max:100'],
        ];
    }
}
