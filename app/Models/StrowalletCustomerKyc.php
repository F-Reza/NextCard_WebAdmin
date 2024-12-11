<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StrowalletCustomerKyc extends Model
{
    use HasFactory;
    protected $appends = ['idImageData','faceImageData'];
    protected $guarded = ['id'];


    protected $casts = [
        'user_id'       => 'integer',
        'id_image'      => 'string',
        'face_image'    => 'string',
    ];
    public function user() {
        return $this->belongsTo(User::class);
    }
    public function getIdImageDataAttribute() {
        $image = $this->attributes['id_image']??$this->id_image??null;
        if($image == null) {
            return files_asset_path('profile-default');
        }else if(filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }else {
            return files_asset_path("card-kyc-images") . "/" . $image;
        }
    }
    public function getFaceImageDataAttribute() {
        $image = $this->attributes['face_image'] ?? $this->face_image ?? null;
        if($image == null) {
            return files_asset_path('profile-default');
        }else if(filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }else {
            return files_asset_path("card-kyc-images") . "/" . $image;
        }
    }
}
