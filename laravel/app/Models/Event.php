<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\TenantScope; // Ensure TenantScope is imported here.
class Event extends Model
{
    use HasFactory;
    protected $fillable = ['tenant_id', 'ad_id', 'user_id', 'event_type', 'data', 'occurred_at'];
    protected $casts = ['data' => 'json'];
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }
}
