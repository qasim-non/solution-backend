<?php

namespace App\Http\Controllers;

use App\Http\Requests\projectRequestsRequest;
use App\Models\Request;

class RequestController extends Controller
{
    public function returnAllRequests(projectRequestsRequest $request)
    {
        $requestValidated = $request->validated();

        $requests = Request::getAllRequests()->when(isset($requestValidated['search']), function ($query) use ($requestValidated) {
            return Request::searchByName($query, $requestValidated['search']);

        })->when(isset($requestValidated['system_type']), function ($query) use ($requestValidated) {
            return Request::searchBySystemTypes($query, $requestValidated['system_type']);

        })->when(isset($requestValidated['status']), function ($query) use ($requestValidated) {
            return Request::searchByStatus($query, $requestValidated['status']);

        })->when(isset($requestValidated['start_date']), function ($query) use ($requestValidated) {
            return Request::searchByDate($query, $requestValidated['start_date'], $requestValidated['end_date']);
        });

        return response()->json(['message' => 'All requests retrieved successfully.', 'data' => $requests->get()], 200);
    }

    public function requestComplete(Request $request)
    {
        if ($request->status == 'completed') {
            return response()->json(['message' => 'The request status is already completed.'], 400);
        }
        Request::requestToComplete($request['id']);

        return response()->json(['message' => 'Request status updated to completed.'], 200);
    }

    public function requestRevert(Request $request)
    {
        if ($request->status == 'pending') {
            return response()->json(['message' => 'The request status is already pending.'], 400);
        }

        Request::requestToPending($request['id']);

        return response()->json(['message' => 'Request status updated to pending.'], 200);
    }
}
