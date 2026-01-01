<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Section;

class PermissionController extends Controller
{
    public function myPermissions(Request $request)
    {
        $user = $request->user();

        $sections = Section::with(['modules.permissions.action'])->get();

        $response = [];

        foreach ($sections as $section) {
            foreach ($section->modules as $module) {
                foreach ($module->permissions as $perm) {

                    if ($user->hasPermission($perm->name)) {
                        $response[$section->slug][$module->slug][] = $perm->action->name;
                    }

                }
            }
        }

        return response()->json([
            'permissions' => $response
        ]);
    }

    public function allPermissions()
    {
        $sections = Section::with('modules.permissions.action')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sections->map(function ($section) {
                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'modules' => $section->modules->map(function ($module) {
                        return [
                            'id' => $module->id,
                            'name' => $module->name,
                            'actions' => $module->permissions->map(fn ($p) => [
                                'permission_id' => $p->id,
                                'action' => $p->action->name
                            ])
                        ];
                    })
                ];
            })
        ]);
    }
}

