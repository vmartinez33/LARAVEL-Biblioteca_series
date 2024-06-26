<?php

namespace App\Http\Controllers;

use App\Actor;
use App\Director;
use App\Language;
use App\Platform;
use App\Serie;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;

class SeriesController extends Controller
{
    const PAGINATE_SIZE = 5;
    public function index(Request $request) {   

        $text = null;
        if($request->has('text')) {

            $text = $request->text;

            $series = Serie::whereHas('platform', function (Builder $query) use ($text) {
                $query->where('name', 'like', '%' . $text . '%');
            })->orWhereHas('director', function (Builder $query) use ($text) {
                $query->where('name', 'like', '%' . $text . '%')->orWhere('first_surname', 'like', '%' . $text . '%')
                ->orWhere('second_surname', 'like', '%' . $text . '%')->orWhere('dni', 'like', '%' . $text . '%');
            })->orWhereHas('actors', function (Builder $query) use ($text) {
                $query->where('name', 'like', '%' . $text . '%')->orWhere('first_surname', 'like', '%' . $text . '%')
                ->orWhere('second_surname', 'like', '%' . $text . '%')->orWhere('dni', 'like', '%' . $text . '%');
            })->orWhereHas('audioLanguages', function (Builder $query) use ($text) {
                $query->where('name', 'like', '%' . $text . '%')->orWhere('iso_code', 'like', '%' . $text . '%');
            })->orWhereHas('subtitlesLanguages', function (Builder $query) use ($text) {
                $query->where('name', 'like', '%' . $text . '%')->orWhere('iso_code', 'like', '%' . $text . '%');
            })->orWhere('title', 'like', '%' . $text . '%')->paginate(self::PAGINATE_SIZE);

        } else {
            $series = Serie::paginate(self::PAGINATE_SIZE);
        }
        return view('series.index', ['series' => $series, 'text' => $text]);
        
    }

    public function create() {
        $platforms = Platform::all();
        $directors = Director::all();
        $actors = Actor::all();
        $languages = Language::all();
        return view('series.create', ['platforms' => $platforms, 'directors' => $directors, 'actors' => $actors, 'languages' => $languages]);
    }

    public function store(Request $request) {
        $this->validateSeries($request)->validate();

        if($route = $this->validateDB($request)) {
            return $route;
        } else {
            $series = new Serie();
            $series->title = $request->seriesTitle;
            $series->platform_id = $request->seriesPlatform;
            $series->director_id = $request->seriesDirector;
            $series->save();
            $series->actors()->attach($request->seriesActors);
            $series->audioLanguages()->attach($request->seriesAudioLanguages);
            $series->subtitlesLanguages()->attach($request->seriesSubtitlesLanguages);
            
            return redirect()->route('series.index')->with('success', Lang::get('alerts.series_created_successfully'));
        }
    }

    public function edit(Serie $series) {
        $platforms = Platform::all();
        $directors = Director::all();
        $actors = Actor::all();
        $languages = Language::all();
        return view('series.create',  ['series' => $series , 'platforms' => $platforms, 'directors' => $directors, 'actors' => $actors, 'languages' => $languages]);
    }

    public function update(Request $request, Serie $series) {
        $this->validateSeries($request)->validate();

        if($route = $this->validateDB($request, $series->id)) {
            return $route;
        } else {
            $series->title = $request->seriesTitle;
            $series->platform_id = $request->seriesPlatform;
            $series->director_id = $request->seriesDirector;
            $series->save();
            $series->actors()->sync($request->seriesActors);
            $series->audioLanguages()->sync($request->seriesAudioLanguages);
            $series->subtitlesLanguages()->sync($request->seriesSubtitlesLanguages);
            
            return redirect()->route('series.index')->with('success', Lang::get('alerts.series_updated_successfully'));
        }
    }

    public function delete(Request $request, Serie $series) {
        if($series != null) {
            $series->actors()->detach();
            $series->audioLanguages()->detach();
            $series->subtitlesLanguages()->detach();
            $series->delete();
            return redirect()->route('series.index')->with('success', Lang::get('alerts.series_deleted_successfully'));
        }

        return redirect()->route('series.index')->with('danger', Lang::get('alerts.series_deleted_error'));
    }

    private function validateSeries($request) {
        return Validator::make($request->all(), [
            'seriesTitle' => ['required', 'string', 'max:200'],
            'seriesPlatform' => ['required', 'numeric', 'gt:0'],
            'seriesDirector' => ['required', 'numeric', 'gt:0'],
            'seriesActors' => ['required', 'array', 'min:1'],
            'seriesActors.*' => ['numeric', 'gt:0'],
            'seriesAudioLanguages' => ['required', 'array', 'min:1'],
            'seriesAudioLanguages.*' => ['numeric', 'gt:0'],
            'seriesSubtitlesLanguages' => ['required', 'array', 'min:1'],
            'seriesSubtitlesLanguages.*' => ['numeric', 'gt:0'],
        ]);
    }

    private function validateDB($request, $series_id = 0) {

        $actorsValues = array_unique($request->seriesActors);
        $actorsInDB = Actor::whereIn('id', $actorsValues)->count();

        $audioLanguagesValues = array_unique($request->seriesAudioLanguages);
        $audioLanguagesInDB = Language::whereIn('id', $audioLanguagesValues)->count();

        $subtitlesLanguagesValues = array_unique($request->seriesSubtitlesLanguages);
        $subtitlesLanguagesInDB = Language::whereIn('id', $subtitlesLanguagesValues)->count();
        
        if(Serie::where([['title', $request->seriesTitle], ['id', '!=', $series_id]])->exists()) {
            $request->flashExcept('seriesTitle');
            return redirect()->back()->with('danger', Lang::get('alerts.series_title_exists_error'));
        } 
        elseif(Platform::where('id', $request->seriesPlatform)->doesntExist()) {
            $request->flash();
            return redirect()->back()->with('danger', Lang::get('alerts.series_platform_doesntExist_error'));
        }
        elseif(Director::where('id', $request->seriesDirector)->doesntExist()) {
            $request->flash();
            return redirect()->back()->with('danger', Lang::get('alerts.series_director_doesntExist_error'));
        }
        elseif($actorsInDB !== count($actorsValues)) {
            $request->flash();
            return redirect()->back()->with('danger', Lang::get('alerts.series_actors_doesntExist_error'));
        }
        elseif($audioLanguagesInDB !== count($audioLanguagesValues)) {
            $request->flash();
            return redirect()->back()->with('danger', Lang::get('alerts.series_audioLanguages_doesntExist_error'));
        }
        elseif($subtitlesLanguagesInDB !== count($subtitlesLanguagesValues)) {
            $request->flash();
            return redirect()->back()->with('danger', Lang::get('alerts.series_subtitlesLanguages_doesntExist_error'));
        }
    }
}
