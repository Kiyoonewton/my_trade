<?php

namespace App\Http\Controllers;

use App\Models\EditMatches;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdjustMatch_idController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function EditMatches()
    {
        $matches = DB::table('matches')->get();
        foreach ($matches as $match) {
            DB::table('over_or_unders')
                ->where(function ($query) use ($match) {
                    $query->where('home', $match->team1)
                        ->where('away', $match->team2);
                })
                ->orWhere(function ($query) use ($match) {
                    $query->where('away', $match->team1)  // Reverse team1 and team2
                        ->where('home', $match->team2);
                })
                ->update(['match_id' => $match->uuid]);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(EditMatches $EditMatches)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EditMatches $EditMatches)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EditMatches $EditMatches)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EditMatches $EditMatches)
    {
        //
    }
}
