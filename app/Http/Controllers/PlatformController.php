<?php

namespace App\Http\Controllers;

use App\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;

class PlatformController extends Controller
{
    const PAGINATE_SIZE = 5;
    public function index(Request $request) {

        $platformName = null;
        if($request->has('platformName')) {
            $platformName = $request->platformName;
            $platforms = Platform::where('name', 'like', '%' . $platformName . '%')->paginate(self::PAGINATE_SIZE);
        } else {
            $platforms = Platform::paginate(self::PAGINATE_SIZE);
        }

        return view('platforms.index', ['platforms' => $platforms, 'platformName' => $platformName]);

    }

    public function create() {
        return view('platforms.create');
    }

    public function store(Request $request) {
        $this->validatePlatform($request)->validate();

        if($route = $this->validateName($request)) {
            return $route;
        } else {
            $platform = new Platform();
            $platform->name = $request->platformName;
            $platform->save();
    
            return redirect()->route('platforms.index')->with('success', Lang::get('alerts.platforms_created_successfully'));
        }  
    }

    public function edit(Platform $platform) {
        return view('platforms.create', ['platform' => $platform]);
    }

    public function update(Request $request, Platform $platform) {
        $this->validatePlatform($request)->validate();

        if($route = $this->validateName($request, $platform->id)) {
            return $route;
        } else {  
            $platform->name = $request->platformName;
            $platform->save();

            return redirect()->route('platforms.index')->with('success', Lang::get('alerts.platforms_updated_successfully'));
        }
    }

    public function delete(Request $request, Platform $platform) {
        if($platform == null) {
            return redirect()->route('platforms.index')->with('danger', Lang::get('alerts.platforms_deleted_error'));
        } 
        elseif(count($platform->series) > 0) {
            return redirect()->route('platforms.index')->with('danger', Lang::get('alerts.platforms_relation_exists')); 
        }

        $platform->delete();
        return redirect()->route('platforms.index')->with('success', Lang::get('alerts.platforms_deleted_successfully'));
    }

    private function validatePlatform($request) {
        return Validator::make($request->all(), [
            'platformName' => ['required', 'string', 'max:50']
        ]);
    }

    private function validateName($request, $platform_id = 0) {
        if(Platform::where([['name', $request->platformName], ['id', '!=', $platform_id]])->exists()) {
            $request->flashExcept('platformName');
            return redirect()->back()->with('danger', Lang::get('alerts.platforms_name_exists_error'));
        }
    }
}
