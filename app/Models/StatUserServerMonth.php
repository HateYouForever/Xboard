<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\StatUserServerMonth
 *
 * @property int $id
 * @property int $user_id 用户ID
 * @property int $server_id 节点ID
 * @property string $server_type 节点类型
 * @property int $u 上行流量
 * @property int $d 下行流量
 * @property int $record_at 记录时间（月初）
 * @property int $created_at
 * @property int $updated_at
 */
class StatUserServerMonth extends Model
{
    protected $table = 'v2_stat_user_server_month';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    public function server()
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
