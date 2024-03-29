<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        return view('employees.list', ['request' => $request]);
    }

    public function indexData(Request $request)
    {
        if ($request->expectsJson()) {
            $user = auth()->user();
            $employees = $user
                ->employees()
                ->select(['id', 'name', 'mobile', 'address', 'status']);

            if ($request->filled('searchQuery') && $request->searchQuery != '0') {
                $request->searchQuery = ltrim($request->searchQuery, '0');
                $employees->where('name', 'like', '%' . $request->searchQuery . '%')
                    ->orWhere('mobile', 'like', '%' . $request->searchQuery . '%');
            }

            if ($request->filled('status')) {
                $employees = $employees->where('status', $request->status);
            }

            $employees = $employees
                ->orderBy('id', 'DESC')
                ->paginate(20, ['*'], 'page', $request->page ?? 1);

            $employees->data = $employees->each(function ($c) {
                $c->collection_count = $c->payments()->sum('amount');
                return $c;
            });

            return $employees;
        }

        return false;
    }

    public function create()
    {
        return view('employees.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'role' => ['bail', 'required', 'numeric', 'in:1'],
            'name' => ['bail', 'required', 'string', 'max:255'],
            'f_name' => ['bail', 'nullable', 'string', 'max:255'],
            'm_name' => ['bail', 'nullable', 'string', 'max:255'],
            'mobile' => ['bail', 'required', 'numeric', 'digits:11', 'unique:employees'],
            'nid' => ['bail', 'nullable', 'numeric', 'unique:employees'],
            'address' => ['required'],
            'photo' => ['bail', 'nullable', 'image', 'max:2048'],
            'username' => ['bail', 'required', 'alpha_num', 'max:255', 'unique:employees'],
            'password' => ['bail', 'required', 'string', 'confirmed']
        ]);

        $photo = null;
        if ($request->has('photo')) {
            $photo = $request->file('photo')->store('employee', 'public');
        }

        $employee = auth()->user()->employees()->create([
            'name' => $request->name,
            'f_name' => $request->f_name,
            'm_name' => $request->m_name,
            'mobile' => $request->mobile,
            'nid' => $request->nid,
            'role' => $request->role,
            'address' => $request->address,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'photo' => $photo
        ]);

        return redirect()
            ->back()
            ->with('message', "Employee $employee->name successfully created!");
    }


    public function show(Employee $employee)
    {
        $user = auth()->user();

        if ($employee->user_id != $user->id) {
            abort(404);
        }

        return view('employees.show', ['employee' => $employee]);
    }

    public function edit(Employee $employee)
    {
        $user = auth()->user();

        if ($employee->user_id != $user->id) {
            abort(404);
        }

        return view('employees.edit', ['employee' => $employee]);
    }


    public function update(Request $request, Employee $employee)
    {
        $user = auth()->user();

        if ($employee->user_id != $user->id) {
            abort(404);
        }

        $request->validate([
            'name' => ['bail', 'required', 'string', 'max:255'],
            'f_name' => ['bail', 'nullable', 'string', 'max:255'],
            'm_name' => ['bail', 'nullable', 'string', 'max:255'],
            'mobile' => ['bail', 'required', 'numeric', 'digits:11'],
            'nid' => ['bail', 'nullable', 'numeric'],
            'address' => ['required']
        ]);

        if ($request->mobile != $employee->mobile) {
            if (Employee::where('mobile', $request->mobile)->exists()) {
                return redirect()->back()->withErrors('Mobile must be unique!');
            }
        }

        if ($request->filled('nid') && $request->nid != $employee->nid) {
            if (Employee::where('nid', $request->nid)->exists()) {
                return redirect()->back()->withErrors('NID must be unique!');
            }
        }

        $employee->name = $request->name;
        $employee->f_name = $request->f_name;
        $employee->m_name = $request->m_name;
        $employee->mobile = $request->mobile;
        $employee->nid = $request->nid ?? null;
        $employee->address = $request->address;

        $employee->save();

        return redirect()
            ->back()
            ->with('message', "Employee $employee->name successfully updated!");
    }

    public function changeStatus(Request $request, Employee $employee)
    {
        $user = auth()->user();

        if ($employee->user_id != $user->id) {
            abort(404);
        }

        if ($request->expectsJson()) {
            $request->validate([
                'status' => ['required', 'numeric', 'in:1,2,0']
            ]);

            $employee->status = $request->status;
            $employee->save();

            return $employee->status;
        }

        return false;
    }

    public function changePassword(Request $request, Employee $employee)
    {
        $user = auth()->user();

        if ($employee->user_id != $user->id) {
            abort(404);
        }

        $request->validate([
            'password' => ['bail', 'required', 'string', 'confirmed']
        ]);

        $employee->password = Hash::make($request->password);
        $employee->save();

        return redirect()
            ->back()
            ->with('message', "Password for employee $employee->name successfully changed!");
    }

    public function changePasswordForm(Employee $employee)
    {
        return view('employees.changePassword', ['employee' => $employee]);
    }

    public function destroy(Request $request, Employee $employee)
    {
        $user = auth()->user();

        if ($employee->user_id != $user->id) {
            abort(404);
        }

        $request->validate([
            'pin' => ['required', 'numeric']
        ]);

        if ($user->pin != $request->pin) {
            return redirect()->back()->withErrors(['errors' => 'Wrong PIN!']);
        }

        if ($employee->delete()) {
            return redirect(route('employees'))->with('message', 'Employee successfully deleted!');
        }

        return redirect()->back()->withErrors(['errors' => 'Delete ERROR!']);
    }

    public function loginForm()
    {
        return view('auth.employee_login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string']
        ]);

        $employee = Employee::where([
            'username' => $request->username,
        ])->first();

        if (!$employee) {
            return redirect()
                ->back()
                ->withErrors(['errors' => 'Username or password is wrong!']);
        }

        if (!Hash::check($request->password, $employee->password)) {
            return redirect()
                ->back()
                ->withErrors(['errors' => 'Username or password is wrong!']);
        }

        if ($employee->status != Employee::STATUS_ACTIVE) {
            return redirect()
                ->back()
                ->withErrors(['errors' => 'Account is not activated by admin!']);
        }

        $request->session()->regenerate();
        $request->session()->put('employee_id', $employee->id);
        Employee::setEmployee($employee->id);

        return redirect(route('dashboard_v2'));
    }

    public function logout()
    {
        session()->forget('employee_id');
        return redirect(route('employee_login'));
    }
}
