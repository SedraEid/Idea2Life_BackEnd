<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use Illuminate\Http\Request;

class RoadmapController extends Controller
{
    
public function getIdeaRoadmap(Request $request, Idea $idea)//جلب خارطة الطريق  للفكرة
{
    $user = $request->user();

    $ideaOwner = $idea->ideaowner;
    $isOwner = $ideaOwner && $ideaOwner->user_id === $user->id;
    $isCommittee = $user->committeeMember && $user->committeeMember->committee_id === $idea->committee_id;

    if (!$isOwner && !$isCommittee) {
        return response()->json(['message' => 'ليس لديك صلاحية الوصول لخارطة الطريق.'], 403);
    }

    $roadmap = $idea->roadmap;

    if (!$roadmap) {
        return response()->json(['message' => 'لا توجد خارطة طريق لهذه الفكرة.'], 404);
    }

    return response()->json([
        'idea_id' => $idea->id,
        'title' => $idea->title,
        'roadmap' => [
            'current_stage' => $roadmap->current_stage,
            'description' => $roadmap->stage_description,
            'progress_percentage' => $roadmap->progress_percentage,
            'last_update' => $roadmap->last_update,
            'next_step' => $roadmap->next_step,
        ]
    ]);
}
}
