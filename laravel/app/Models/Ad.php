<?php
namespace App\Models;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
class Ad extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'content', 'start_time', 'end_time', 'target_audience', 'tenant_id'];
    protected $casts = ['target_audience' => 'json'];
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
        static::creating(function ($ad) {
            if (Auth::check()) {
                $ad->tenant_id = Auth::user()->tenant_id;
            }
        });
    }
}
