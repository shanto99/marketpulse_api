<?php

namespace App\Http\Controllers;

use App\Models\UserManager;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Models\GeoLocation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class MapController extends Controller
{
    public function getLocations($user_id, $start_date, $end_date) {
        $end_date = $end_date." 23:59:59";
        $locations = GeoLocation::where('UserID', $user_id)->whereBetween('AttendanceTime',[$start_date, $end_date])
            ->orderBy('AttendanceTime')->get();

        return response()->json([
            'data' => $locations,
            'status' => 200
        ], 200);
    }

    public function generateReport($user_id, $start_date, $end_date) {
        $locations = GeoLocation::where('UserID', $user_id)->whereBetween('AttendanceTime',[$start_date, $end_date])
            ->orderBy('AttendanceTime')->get();
        $user = UserManager::where('UserID', $user_id)->first();
        $data = [
            'user' => $user,
            'locations' => $locations
        ];
        $pdf = PDF::loadView('report', $data)->setPaper('a4', 'landscape');
        $path = public_path('pdf');
        $fileName =  time().'.'. 'pdf' ;
        $pdf->save($path . '/' . $fileName);

        $pdf = public_path('pdf/'.$fileName);
        return response()->download($pdf);
    }

    public function saveGpsLocation(Request $request)
    {
        $imageName = time().'.'.$request->image->extension();
        try {
            $request->image->move(public_path('images'), $imageName);
        } catch (\Exception $e) {
            return response()->json(['data' => null, 'status' => 500], 200);
        }


        $imagePath = env("APP_URL").'/public/images/'.$imageName;

        $result = GeoLocation::create([
           'Latitude' => $request['lat'],
            'Longitude' => $request['lng'],
            'Comment' => $request['comment'],
            'UserID' => $request['user_id'],
            'AttendanceImage' => $imagePath
        ]);

        if($result) {
            return response()->json(['data' => $result, 'status' => 200], 200);
        } else {
            return response()->json(['data' => $result, 'status' => 500], 500);
        }
    }

    public function in_out_report($start_date, $end_date, $user_id)
    {
        $user = UserManager::find($user_id);
        $userManager = new UserManager();
        $subordinates = $userManager->allSubordinateUsers($user);

        $in_outs = [];

        $users = GeoLocation::with('user')->whereBetween('AttendanceTime',[$start_date, $end_date])->orderBy('AttendanceTime')
            ->get()->groupBy(['UserID', function($attendance) {
                $date = Carbon::parse($attendance['AttendanceTime']);
                return $date->format('Y-m-d');
            }]);

        foreach ($users as $userId => $dates) {
            foreach($dates as $date => $attendances) {
                $count = count($attendances);
                if(count($attendances) > 0) {
                    array_push($in_outs, [
                        'date' => $date,
                        'user_id' => $userId,
                        'user_name' => $attendances[0]->User ? $attendances[0]->User->UserName : '',
                        'report_count' => $count,
                        'in_time' => Carbon::parse($attendances[0]['AttendanceTime'])->format('g:i A'),
                        'in_area' => $attendances[0]['Area'],
                        'out_time' => Carbon::parse($attendances[$count - 1]['AttendanceTime'])->format('g:i A'),
                        'out_area' => $attendances[$count - 1]['Area']
                    ]);
                }
            }
        }

        return response()->json([
            'data' => $in_outs,
            'status' => 200
        ], 200);

    }

    public function get_multi_locations(Request $request)
    {
        $userIds = json_decode($request->userIds);
        $attendances = GeoLocation::with('user')->whereBetween('AttendanceTime',[$request->from, $request->to])
            ->whereIn('UserID', $userIds)->get();

        return response()->json([
            'data' => $attendances,
            'status' => 200
        ], 200);
    }

    public function hit_counts($start_date, $end_date, $userId)
    {
        $hit_counts = [];
        $user = UserManager::find($userId);
        $userManager = new UserManager();
        $subordinates = $userManager->allSubordinateUsers($user);
        $subordinateIds = array_map(function($subordinate) {
            return $subordinate['UserID'];
        }, $subordinates);

        $counts = DB::table('HitCount')
            ->join('UserManager', 'UserManager.UserID', '=', 'HitCount.UserID')
            ->whereBetween('HittingDate', [$start_date, $end_date])
            ->whereIn('HitCount.UserID', $subordinateIds)->get();

        return response()->json([
            'data' => $counts
        ], 200);
    }

}
