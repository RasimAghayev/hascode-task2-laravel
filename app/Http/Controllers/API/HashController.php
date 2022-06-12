<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Hash;
use Illuminate\Http\Request;
use App\Http\Resources\HashResource;
use Illuminate\Support\Facades\DB;

class HashController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $products = Hash::all();
        return response(['hashes' => HashResource::collection($products)]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator =  $request->validate([
            'data' => 'required|min:5',
        ]);

//        if($validator->fails()){
//            return response(['error' => $validator->errors(), 'Validation Error']);
//        }
        $validator['hash'] = sha1($validator['data']);
        $oldHash=$this->showData($validator['data']);
        $validator['data'] = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $validator['data']);
//        dd($oldHash);
        print_r($oldHash);
            die();
        $validator['hash0'] = ($oldHash==0)?sha1($validator['data']):$oldHash['hash0'];

        $hash = Hash::create($validator);

//        return response(['hash' => new HashResource($hash), 'message' => 'Hash created successfully']);
        return response(['hash' => $hash->hash]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Hash  $hash
     * @return \Illuminate\Http\Response
     */
    public function showData($data)
    {
        $clear = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $data);
        $hash = DB::table('hashes')->where('data', $clear)->get();
        if (count($hash)==0){
            return 0;
        }
        return response([$hash]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Hash  $hash
     * @return \Illuminate\Http\Response
     */
    public function show($hash)
    {
        $data = DB::table('hashes')->where('hash0', $hash)->get();
        if (count($data)==0){
            return response(['message' => 'Not found'],404);
        }
        return response(['item'=>new HashResource($data)]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Hash  $hash
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Hash $hash)
    {
        $validator =  $request->validate([
            'data' => 'required|min:5',
        ]);

//        if($validator->fails()){
//            return response(['error' => $validator->errors(), 'Validation Error']);
//        }
        $validator['hash'] = sha1($validator['data']);
        $validator['hash0'] = sha1($validator['hash0']);

        $hash->update($validator);

        return response(['hash' => new HashResource($hash), 'message' => 'Hash updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Hash  $hash
     * @return \Illuminate\Http\Response
     */
    public function destroy(Hash $hash)
    {
        $hash->delete();

        return response(['message' => 'Hash deleted successfully']);
    }
}
