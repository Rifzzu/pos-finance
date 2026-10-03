<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostingRequest;
use App\Http\Resources\PostingResource;
use App\Services\PostingService;

class InternalPostingController extends Controller
{
    public function __construct(private PostingService $postings) {}

    public function store(PostingRequest $request)
    {
        $result = $this->postings->record($request->validated());

        return response()->json([
            'message' => $result['created'] ? 'Posting tercatat.' : 'Posting sudah ada.',
            'data' => new PostingResource($result['posting']),
        ], $result['created'] ? 201 : 200);
    }
}
