<?php

namespace Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HRController extends Controller
{
    public function __construct()
    {
        /*
            كان هذا المتحكّم **بلا حارسٍ واحد**: أيّ موظفٍ مسجَّل دخوله
            يستدعي مساراته مباشرةً. والقراءة تُفصل عن الكتابة لأن من
            يطّلع ليس بالضرورة من يُعدِّل.
        */
        // هيكلُ مواردٍ افتراضيٌّ بلا فعلٍ حقيقي — كلُّه تحت صلاحية لوحة الموارد البشرية
        $this->middleware('permission.role:hr_dashboard.index', ['only' => [
            'index', 'show', 'create', 'destroy', 'edit', 'store', 'update',
        ]]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('hr::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('hr::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('hr::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('hr::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
