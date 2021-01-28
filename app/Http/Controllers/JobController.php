<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
    {
        return view('jobs.list', ['request' => $request]);
    }

    public function indexData(Request $request)
    {
        if ($request->expectsJson()) {
            $user = auth()->user();
            $jobs = $user
                ->jobs()
                ->select(['id', 'name', 'body', 'status']);


            if ($request->filled('searchQuery') && $request->searchQuery != '0') {
                $request->searchQuery = ltrim($request->searchQuery, '0');
                $jobs->where('name', 'ilike', '%' . $request->searchQuery . '%')
                    ->orWhere('body', 'ilike', '%' . $request->searchQuery . '%');
            }

            if ($request->filled('status')) {
                $jobs = $jobs->where('status', $request->status);
            }

            $jobs = $jobs
                ->orderBy('id', 'DESC')
                ->paginate(20, ['*'], 'page', $request->page ?? 1);

            $jobs->data = $jobs->each(function ($c) {
                $c->body = substr($c->body, 0, 180);
                return $c;
            });

            return $jobs;
        }

        return false;
    }

    public function create()
    {
        return view('jobs.create');
    }


    public function store(Request $request)
    {
        $request->validate([
            'employee' => ['bail', 'required', 'numeric', 'exists:employees,id'],
            'name' => ['bail', 'required', 'string'],
            'body' => ['required']
        ]);

        $user = auth()->user();
        $employee = $user->employees()->findOrFail($request->employee);

        $job = $employee->jobs()->create([
            'user_id' => $user->id,
            'name' => $request->name,
            'body' => $request->body,
        ]);

        return redirect()
            ->back()
            ->with('message', 'Job created successfully!');
    }

    public function show(Job $job)
    {
        $user = auth()->user();

        if ($job->user_id != $user->id) {
            abort(404);
        }

        return view('jobs.show', ['job' => $job]);
    }

    public function edit(Job $job)
    {
        $user = auth()->user();

        if ($job->user_id != $user->id) {
            abort(404);
        }

        return view('jobs.edit', ['job' => $job]);
    }

    public function update(Request $request, Job $job)
    {
        $user = auth()->user();

        if ($job->user_id != $user->id) {
            abort(404);
        }

        $request->validate([
            'name' => ['bail', 'required', 'string'],
            'body' => ['required']
        ]);

        $job->name = $request->name;
        $job->body = $request->body;
        $job->save();

        return redirect()
            ->back()
            ->with('message', 'Job updated successfully!');
    }

    public function destroy(Request $request, Job $job)
    {
        $user = auth()->user();

        if ($job->user_id != $user->id) {
            abort(404);
        }

        $request->validate([
            'pin' => ['required', 'numeric']
        ]);

        if ($user->pin != $request->pin) {
            return redirect()->back()->withErrors(['errors' => 'Wrong PIN!']);
        }

        if ($job->delete()) {
            return redirect(route('jobs'))->with('message', 'Job successfully deleted!');
        }

        return redirect()->back()->withErrors(['errors' => 'Delete ERROR!']);
    }
}
