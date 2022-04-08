<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Uploaded_files;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

// Class for getting all the files
class FileData
{
    public $name;
    public $description;
    public $type;

    public function __construct($name, $description, $type)
    {
        $this->name = $name;
        $this->description = $description;
        $this->type = $type;
    }
}

class MySharedFileData
{
    public $name;
    public $type;
    public $shared_with;

    public function __construct($name, $type, $shared_with)
    {
        $this->name = $name;
        $this->type = $type;
        $this->shared_with = $shared_with;
    }
}

class FileDataSharedWith
{
    public $name;
    public $description;
    public $type;
    public $by_user;

    public function __construct($name, $description, $type, $by_user)
    {
        $this->name = $name;
        $this->description = $description;
        $this->type = $type;
        $this->by_user = $by_user;
    }
}

class AuthController extends Controller
{

    public function working(Request $request)
    {
        return response()->json([
            'message' => "Server is reached"
        ], 200);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' =>   'required|max:191',
            'email' => 'required|email|max:191|unique:users,email',
            'password' => 'required|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Invalid data"
            ], 401);
        } else {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            $user->createToken($user->email . '_token')->plainTextToken;
            return response()->json([
                'message' => 'Registration success'
            ], 200);
        }
    }

    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        } else {

            $token = $user->createToken($user->email)->plainTextToken;
            if ($request->mobile) {
                return response()->json([
                    'message' => 'Welcome',
                    'token' => $token
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Welcome'
                ], 200)->withCookie(cookie('access-token', $token, 7 * 24 * 60));
            }
        }
    }

    public function profile()
    {
        if (Auth::check()) {
            return response()->json([
                'name' => Auth::user()->name,
                'message' => 'Success'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Failed'
            ], 401);
        }
    }

    public function logout(Request $request)
    {
        Auth::user()->tokens->each(function ($token, $key) {
            $token->delete();
        });
        return response()->json([
            'message' => 'Hope to see you soon'
        ], 200)->withCookie(cookie('access-token', '', 1 * 0.01));
    }

    public function myUploadedFiles(Request $request)
    {
        $results = Uploaded_files::where('by_user', Auth::user()->id)->get();
        if ($results) {
            $files = [];
            foreach ($results as $index) {
                $file = new FileData($index->name, $index->description, $index->type);
                $files[] = $file;
            }


            return response()->json([
                'files' => $files
            ], 200);
        } else {
            return response()->json([
                'files' => "Nothing was found"
            ], 404);
        }
    }

    public function mySharedFiles(Request $request)
    {

        $results = Uploaded_files::where('by_user', Auth::user()->id)->where('shared_with', '!=', '[]')->get();
        if ($results) {
            $files = [];
            foreach ($results as $index) {
                $file = new MySharedFileData($index->name, $index->type, $index->shared_with);
                $files[] = $file;
            }
            return response()->json([
                'files' => $files
            ], 200);
        } else {
            return response()->json([
                'message' => "Nothing was found"
            ], 404);
        }
    }

    public function filesSharedWithMe(Request $request)
    {
        $results = Uploaded_files::whereJsonContains('shared_with', Auth::user()->email)->get();
        if ($results) {
            $files = [];
            foreach ($results as $index) {
                $user = User::where('id', $index->by_user)->first();
                $file = new FileDataSharedWith($index->name, $index->description, $index->type, $user->email);
                $files[] = $file;
            }
            return response()->json([
                'files' => $files
            ], 200);
        } else {
            return response()->json([
                'files' => "Nothing was found"
            ], 404);
        }
    }

    public function uploadFile(Request $request)
    {
        if (Auth::check()) {
            if ($request->hasFile('document')) {
                $description = $request->description;
                if ($description == null) {
                    $description = "No description provided";
                }
                Uploaded_files::create([
                    "description" => $description,
                    "name" => time() . '-' . $request->file('document')->getClientOriginalName(),
                    "type" => $request->file('document')->getMimeType(),
                    'shared_with' => [],
                    "by_user" => Auth::user()->id,
                    "path" => $request->file('document')->storeAs("public/userFiles/" . Auth::user()->id, time() . '-' . str_replace(' ', '_', $request->file('document')->getClientOriginalName()))
                ]);
                return response()->json([
                    'message' => 'File was uploaded successfully'
                ], 200);
            };
            //End
        } else {
            return response()->json([
                'message' => 'Unable to preform this action'
            ], 400);
        }
    }


    public function downloadFile(Request $request)
    {
        if ($request->mobile) {
            // For mobile
            if ($request->shared_file) {
                $file = Uploaded_files::where('name', $request->name)->where('description', $request->description)->whereJsonContains('shared_with', Auth::user()->email)->first();
            } else {
                $file = Uploaded_files::where('name', $request->name)->where('description', $request->description)->where('by_user', Auth::user()->id)->first();
            }
            if ($file) {
                // /public needs to be removed
                $url = asset('storage/' . $file->path);
                return response()->json([
                    'url' => str_replace("public/", "", $url),
                    'name' => $file->name,
                    'type' => $file->type
                ], 200);
            } else {
                return response()->json([
                    'message' => 'File not found'
                ], 404);
            }
        } else {
            // For web
            if ($request->shared_file) {
                $file = Uploaded_files::where('name', $request->name)->where('description', $request->description)->whereJsonContains('shared_with', Auth::user()->email)->first();
            } else {
                $file = Uploaded_files::where('name', $request->name)->where('description', $request->description)->where('by_user', Auth::user()->id)->first();
            }
            if ($file) {
                return Storage::download($file->path, substr($file->name, 11));
            } else {
                return response()->json([
                    'message' => 'File not found'
                ], 404);
            }
        }
    }

    public function deleteFile(Request $request)
    {
        $file = Uploaded_files::where('name', $request->name)->where('description', $request->description)->where('by_user', Auth::user()->id)->first();
        if ($file) {
            $result = Storage::delete($file->path);
            $file->delete();
            if ($result) {
                return response()->json([
                    'message' => "File was deleted"
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Something went wrong please try again'
                ], 400);
            }
        } else {
            return response()->json([
                'message' => 'File not found'
            ], 404);
        }
    }
    public function shareFile(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        } else {
            $file = Uploaded_files::where('name', $request->name)->where('description', $request->description)->where('by_user', Auth::user()->id)->first();
            $shared_users = $file->shared_with;
            if (in_array($user->email, $shared_users)) {
                return response()->json([
                    'message' => 'You are already sharing this file with the user'
                ], 400);
            } elseif ($request->email == Auth::user()->email) {
                return response()->json([
                    'message' => "You can't share the file with yourself."
                ], 400);
            } else {

                $shared_users[] = $user->email;
                $file->shared_with = $shared_users;
                $file->save();
                return response()->json([
                    'message' => 'The file is being shared with the user'
                ], 200);
            }
        }
    }

    public function unShareFile(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        } else {
            $file = Uploaded_files::where('name', $request->name)->where('type', $request->type)->where('by_user', Auth::user()->id)->first();
            $shared_users = $file->shared_with;
            if (($key = array_search($request->email, $shared_users)) !== false) {
                array_splice($shared_users, $key, 1);

                $file->shared_with = $shared_users;
                $file->save();
                return response()->json([
                    'message' => "File is no longer shared with the user"
                ], 200);
            } else {
                return response()->json([
                    'message' => "User was not found"
                ], 404);
            }
        }
    }

    public function unFollowFile(Request $request)
    {
        $user = User::where('email', $request->by_user)->first();
        $file = Uploaded_files::where('name', $request->name)->where('description', $request->description)->where('by_user', $user->id)->first();
        $shared_users = $file->shared_with;
        if (($key = array_search(Auth::user()->email, $shared_users)) !== false) {
            array_splice($shared_users, $key, 1);
            $file->shared_with = $shared_users;
            $file->save();
            return response()->json([
                'message' => "You are no longer following this file"
            ], 200);
        } else {
            return response()->json([
                'message' => "Something wen't wrong please try again later"
            ], 400);
        }
    }
}
