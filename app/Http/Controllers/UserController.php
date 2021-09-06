<?php

namespace App\Http\Controllers;

use App\Models\LevelOne;
use App\Models\LevelThree;
use App\Models\LevelTwo;
use App\Models\UserManager;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function get_all_users(Request $request) {
        $level = $request->route('level');
        $userId = $value = $request->header('userid');
        $levels = ['Level10', 'Level4', 'Level3', 'Level2', 'Level1'];
        $levelIndex = array_search($level, $levels);

        if($levelIndex === 0) {
            $users = UserManager::all();

        } else {
            $user = UserManager::where('UserID', $userId)->first();
            $subordinateUsers = $user->allSubordinateUsers($user);
            $users = array_merge($subordinateUsers, [$user]);
        }

        foreach ($users as $user) {
            $user['Supervisor'] = $this->getSupervisor($user);
        }

        return response()->json(['data' => $users, 'userid' => $userId, 'status' => 200],  200);
    }

    public function getSupervisor($user) {
        $userType = $user->UserType;
        $supervisorId = '';
        if($userType === 'Level1') {
            $level1 = LevelOne::where('Level1', $user->UserID)->first();
            if($level1) {
                $supervisorId = $level1->Level2;
            }

        } else if($userType === 'Level2') {
            $level2 = LevelTwo::where('Level2', $user->UserID)->first();
            if($level2) {
                $supervisorId = $level2->Level3;
            }
        }  else if($userType === 'Level3') {
            $level3 = LevelThree::where('Level3', $user->UserID)->first();
            if($level3) {
                $supervisorId = $level3->Level4;
            }
        }

        if($supervisorId !== '') {
            return UserManager::where('UserID', $supervisorId)->first();
        } else {
            return null;
        }
    }

    public function get_users_by_type($level) {
        $users = UserManager::where('UserType', $level)->get();
        return response()->json(['data' => $users, 'status' => 200],  200);
    }

    public function get_user_details($id=null) {
        if($id) {
            $user = UserManager::where('UserID', $id)->firstOrFail();
            return response()->json(['data' => $user, 'status' => 200], 200);
        } else {
            return response()->json(['data' => null, 'status' => 404], 404);
        }
    }

    public function levelOne(Request $request) {
        $level1 = $request['Level1'];
        $level2 = $request['Level2'];
        $insertedLevel1 = LevelOne::where('Level1', $level1)->first();
        if($insertedLevel1) {
            $result = $insertedLevel1->update([
                'Level2' => $level2
            ]);
        } else {
            $result = LevelOne::create([
                'Level1' => $level1,
                'Level2' => $level2
            ]);
        }

        return response()->json(['data' => $result, 'status' => 200],  200);
    }

    public function levelTwo(Request $request) {
        $level2 = $request['Level2'];
        $level3 = $request['Level3'];

        $insertedLevel2 = LevelTwo::where('Level2', $level2)->first();
        if($insertedLevel2) {
            $result = $insertedLevel2->update([
                'Level3' => $level3
            ]);
        } else {
            $result = LevelTwo::create([
                'Level2' => $level2,
                'Level3' => $level3
            ]);
        }
        return response()->json(['data' => $result, 'status' => 200],  200);
    }

    public function levelThree(Request $request) {
        $level3 = $request['Level3'];
        $level4 = $request['Level4'];

        $insertedLevel3 = LevelThree::where('Level3', $level3)->first();

        if($insertedLevel3) {
            $result = $insertedLevel3->update([
                'Level4' => $level4
            ]);
        } else {
            $result = LevelThree::create([
                'Level3' => $level3,
                'Level4' => $level4
            ]);
        }
        return response()->json(['data' => $result, 'status' => 200],  200);
    }
}
