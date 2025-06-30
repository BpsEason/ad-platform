# AdStackX - 模組化廣告平台

AdStackX 是一個專為廣告管理打造的多租戶平台，使用 **Laravel**、**FastAPI** 和 **Vue 3** 構建，支援個人化廣告推薦、即時數據分析、RBAC 權限控制和自動化部署。核心功能包括廣告管理（創建、編輯、投放）、推薦引擎（基於用戶行為）、報表生成（展示、點擊、轉換率）和高效監控（Prometheus + Grafana）。它適合需要多品牌運營和高擴展性的廣告企業，具備 SaaS 潛力。

**注意**：本倉庫（https://github.com/BpsEason/ad-platform.git）僅包含核心程式碼（例如自定義控制器、模型、前端組件）。基本 Laravel 框架程式碼（例如 `app/Models/User.php`、路由檔案）和依賴（PHP、Python、Node.js 模組）需自行新增。請按照下方「初始化 Laravel 專案」和「安裝依賴」步驟完成設置。

## 系統亮點

AdStackX 的設計結合現代化技術棧和高效架構，提供以下核心優勢：

- **多租戶架構**：透過 `X-Tenant-ID` 和 Laravel 的 `TenantScope`，實現數據隔離，支援多品牌共用系統，降低運營成本。
- **高效推薦引擎**：FastAPI 結合 Kafka 事件流和 Redis 快取，提供即時個人化廣告推薦，支援高併發場景。
- **即時報表與監控**：整合 Chart.js 和 ECharts 打造動態報表，結合 Prometheus 和 Grafana 提供性能監控和錯誤追蹤。
- **靈活的權限控制**：使用 Spatie 的 RBAC 套件，支援細粒度權限管理，確保安全且可追溯。
- **自動化部署**：Docker Compose 實現容器化，GitHub Actions 支援 CI/CD，Trivy 掃描漏洞，確保快速且安全部署。
- **可擴展性**：模組化設計支援未來整合 AI 模型（如協同過濾）、日誌系統（ELK Stack）或雲端部署（Kubernetes、AWS ECS）。

## 系統架構

以下是 AdStackX 的系統架構圖，使用 Mermaid 流程圖語法，採用垂直佈局，確保租戶與 Laravel 後端分離且圖表寬度適中。

```mermaid
graph TD
  subgraph 租戶
    TenantA[租戶 A]
    TenantB[租戶 B]
  end

  TenantA -->|API 請求| Laravel
  TenantB -->|API 請求| Laravel

  subgraph Laravel 後端
    Laravel[Laravel]
    Auth[認證]
    RBAC[權限控制]
    Ads[廣告管理]
    Reports[報表]
    APIs[API]
    Laravel --> Auth
    Laravel --> RBAC
    Laravel --> Ads
    Laravel --> Reports
    Laravel --> APIs
  end

  Laravel -->|事件| FastAPI
  Laravel <-->|推薦| FastAPI

  subgraph FastAPI 推薦
    FastAPI[FastAPI]
    CF[推薦邏輯]
    FastAPI --> CF
  end

  Reports --> Dashboard[Vue.js 儀表板]
  Dashboard -->|Token 與 Tenant-ID| Laravel

  subgraph 基礎設施
    MySQL[MySQL]
    Redis[Redis]
    Kafka[Kafka]
    Laravel --> MySQL
    FastAPI --> MySQL
    FastAPI --> Redis
    FastAPI --> Kafka
  end

  subgraph DevOps
    Traefik[Traefik]
    Docker[Docker]
    CI[CI/CD]
    Traefik --> Laravel
    Traefik --> FastAPI
    Traefik --> Dashboard
    Docker --> Laravel
    Docker --> FastAPI
    Docker --> Dashboard
    CI --> Docker
  end

  classDef highlight fill:#fdf6e3,stroke:#268bd2,stroke-width:2px
  class Laravel,FastAPI,Dashboard highlight
```

**說明**：
- **租戶**：透過 `X-Tenant-ID` 與 Laravel 互動。
- **Laravel 後端**：處理認證、權限控制、廣告管理和報表。
- **FastAPI 推薦**：執行推薦邏輯，與 Kafka 和 Redis 交互。
- **Vue.js 儀表板**：展示報表，透過 Token 與 Laravel 互動。
- **基礎設施**：MySQL 儲存數據，Redis 快取，Kafka 處理事件流。
- **DevOps**：Traefik 路由，Docker 容器化，GitHub Actions 實現 CI/CD。

## 環境要求

- **Docker** 和 **Docker Compose**
- **PHP**（v8.1+）
- **Python**（v3.9+）
- **Node.js**（v18+）
- **服務**：MySQL（v8.0）、Redis（v6.2）、Kafka（v3.5.1）、Zookeeper（v3.8.3）、Prometheus（v2.47.0）、Grafana（v10.2.2）

## 初始化 Laravel 專案

1. **安裝 Laravel**：
   ```bash
   composer create-project laravel/laravel laravel
   cd laravel
   ```

2. **複製核心程式碼**：
   將倉庫的 `laravel/` 目錄複製到 `laravel/app/`。

3. **創建路由檔案**：
   在 `laravel/routes/` 創建 `api.php`：
   ```php
   <?php
   // 定義 API 路由，使用 Sanctum 認證和多租戶中間件
   use Illuminate\Support\Facades\Route;
   use App\Http\Controllers\AdController;
   use App\Http\Controllers\ReportController;

   Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
       Route::get('/ads', [AdController::class, 'index']); // 查詢廣告
       Route::post('/ads', [AdController::class, 'store']); // 創建廣告
       Route::get('/reports/conversions', [ReportController::class, 'conversions']); // 轉換率報表
       Route::get('/reports/events', [ReportController::class, 'events']); // 事件統計報表
   });
   ```

4. **創建 .env 文件**：
   ```bash
   cp .env.example .env
   ```
   編輯 `.env`：
   ```env
   APP_NAME=AdStackX
   APP_ENV=local
   APP_KEY=
   APP_DEBUG=true
   APP_URL=http://ad-api.localhost

   DB_CONNECTION=mysql
   DB_HOST=mysql
   DB_PORT=3306
   DB_DATABASE=ad_platform_db
   DB_USERNAME=user
   DB_PASSWORD=secret

   REDIS_HOST=redis
   KAFKA_BROKER=kafka1:9092
   TRAEFIK_API_KEY=your_traefik_key
   SANCTUM_STATEFUL_DOMAINS=localhost,ad-api.localhost,recommender.localhost,frontend.localhost
   SENTRY_DSN=your_sentry_dsn
   ```

## 安裝依賴

### 1. Laravel（PHP 依賴）
編輯 `laravel/composer.json`：
```json
{
    "require": {
        "php": "^8.1",
        "laravel/framework": "^10.0",
        "laravel/sanctum": "^3.2",
        "spatie/laravel-permission": "^5.10",
        "spatie/laravel-activitylog": "^4.7",
        "darkaonline/l5-swagger": "^8.5"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    }
}
```
安裝：
```bash
cd laravel
composer install
```

### 2. FastAPI（Python 依賴）
創建 `fastapi/requirements.txt`：
```text
fastapi==0.95.0
uvicorn==0.20.0
pydantic==1.10.7
aiokafka==0.8.0
redis==4.5.4
mysql-connector-python==8.0.33
slowapi==0.1.8
sentry-sdk==1.40.0
python-jose[cryptography]==3.3.0
```
安裝：
```bash
cd fastapi
pip install -r requirements.txt
```

### 3. Vue 3（Node.js 依賴）
創建 `frontend/package.json`：
```json
{
    "dependencies": {
        "vue": "^3.2.47",
        "axios": "^1.4.0",
        "chart.js": "^4.4.0",
        "echarts": "^5.4.3"
    },
    "devDependencies": {
        "@vitejs/plugin-vue": "^4.0.0",
        "vite": "^4.1.4",
        "jest": "^29.5.0",
        "@testing-library/vue": "^7.0.0"
    },
    "scripts": {
        "dev": "vite",
        "build": "vite build",
        "test": "jest"
    }
}
```
安裝：
```bash
cd frontend
npm install
```

## 安裝步驟

1. **複製專案**：
   ```bash
   git clone https://github.com/BpsEason/ad-platform.git
   cd ad-platform
   ```

2. **初始化 Laravel 專案**：
   見「初始化 Laravel 專案」。

3. **創建 Docker Compose 配置**：
   在根目錄創建 `docker-compose.yml`：
   ```yaml
   version: '3.8'
   services:
     traefik:
       image: traefik:v2.9
       ports:
         - "80:80"
         - "8080:8080"
       volumes:
         - ./traefik.yml:/etc/traefik/traefik.yml
         - /var/run/docker.sock:/var/run/docker.sock
       networks:
         - ad_network
     laravel:
       build: ./laravel
       environment:
         - APP_ENV=local
         - APP_KEY=${APP_KEY}
         - DB_HOST=mysql
         - DB_DATABASE=${DB_DATABASE}
         - DB_USERNAME=${DB_USERNAME}
         - DB_PASSWORD=${DB_PASSWORD}
         - REDIS_HOST=redis
       volumes:
         - ./laravel:/var/www/html
         - ./docker/entrypoint.sh:/entrypoint.sh
       depends_on:
         - mysql
         - redis
       networks:
         - ad_network
       labels:
         - "traefik.http.routers.laravel.rule=Host(`ad-api.localhost`)"
     fastapi:
       build: ./fastapi
       environment:
         - KAFKA_BROKER=${KAFKA_BROKER}
         - REDIS_HOST=redis
         - DB_HOST=mysql
         - DB_NAME=${DB_DATABASE}
         - DB_USER=${DB_USERNAME}
         - DB_PASSWORD=${DB_PASSWORD}
       depends_on:
         - kafka1
         - redis
         - mysql
       networks:
         - ad_network
       labels:
         - "traefik.http.routers.fastapi.rule=Host(`recommender.localhost`)"
     frontend:
       build: ./frontend
       networks:
         - ad_network
       labels:
         - "traefik.http.routers.frontend.rule=Host(`frontend.localhost`)"
     mysql:
       image: mysql:8.0
       environment:
         - MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD}
         - MYSQL_DATABASE=${DB_DATABASE}
         - MYSQL_USER=${DB_USERNAME}
         - MYSQL_PASSWORD=${DB_PASSWORD}
       volumes:
         - mysql_data:/var/lib/mysql
       networks:
         - ad_network
     redis:
       image: redis:6.2
       networks:
         - ad_network
     zookeeper1:
       image: confluentinc/cp-zookeeper:7.3.0
       environment:
         ZOOKEEPER_CLIENT_PORT: 2181
         ZOOKEEPER_TICK_TIME: 2000
       networks:
         - ad_network
     kafka1:
       image: confluentinc/cp-kafka:7.3.0
       depends_on:
         - zookeeper1
       environment:
         KAFKA_BROKER_ID: 1
         KAFKA_ZOOKEEPER_CONNECT: zookeeper1:2181
         KAFKA_ADVERTISED_LISTENERS: PLAINTEXT://kafka1:9092,EXTERNAL://localhost:9094
         KAFKA_LISTENERS: PLAINTEXT://:9092,EXTERNAL://:9094
         KAFKA_OFFSETS_TOPIC_REPLICATION_FACTOR: 1
       ports:
         - "9094:9094"
       networks:
         - ad_network
     prometheus:
       image: prom/prometheus:v2.47.0
       volumes:
         - ./prometheus.yml:/etc/prometheus/prometheus.yml
       ports:
         - "9090:9090"
       networks:
         - ad_network
     grafana:
       image: grafana/grafana:10.2.2
       ports:
         - "3000:3000"
       environment:
         - GF_SECURITY_ADMIN_PASSWORD=admin
       networks:
         - ad_network
   networks:
     ad_network:
       driver: bridge
   volumes:
     mysql_data:
   ```

4. **創建 Traefik 配置**：
   在根目錄創建 `traefik.yml`：
   ```yaml
   http:
     routers:
       api:
         rule: "Host(`traefik.localhost`)"
         service: api@internal
         middlewares:
           - auth
     middlewares:
       auth:
         basicAuth:
           users:
             - "admin:$apr1$your_hashed_password"
   ```

5. **創建 Laravel 入口腳本**：
   在 `docker/` 目錄創建 `entrypoint.sh`：
   ```bash
   #!/bin/bash
   set -e
   if [ "${APP_KEY}" = "your_generated_key" ]; then
     echo "錯誤：APP_KEY 未設置，請運行 'php artisan key:generate'。" >&2
     exit 1
   fi
   php artisan optimize
   php artisan serve --host=0.0.0.0 --port=80
   ```

6. **生成 Laravel 應用程式金鑰**：
   ```bash
   docker-compose run --rm laravel php artisan key:generate --show
   ```
   將生成的 `APP_KEY` 更新到 `.env`。

7. **運行資料庫遷移**：
   ```bash
   docker-compose exec laravel php artisan migrate --force
   ```

8. **更新 RouteServiceProvider**：
   在 `laravel/app/Providers/RouteServiceProvider.php` 的 `boot` 方法添加：
   ```php
   public function boot()
   {
       $this->configureRateLimiting();
       $this->routes(function () {
           Route::middleware('api')
               ->prefix('api')
               ->group(base_path('routes/api.php'));
           Route::middleware(['api', \App\Http\Middleware\SetTenant::class])
               ->prefix('api')
               ->group(base_path('routes/api.php'));
       });
   }
   ```

9. **添加環境變數驗證**：
   在 `laravel/public/index.php` 添加：
   ```php
   require __DIR__.'/../bootstrap/validate_env.php';
   ```
   創建 `laravel/bootstrap/validate_env.php`：
   ```php
   <?php
   if (!env('APP_KEY')) {
       throw new RuntimeException('Application key not set in .env file.');
   }
   ```

## 功能實現與關鍵程式碼

以下整合 AdStackX 的核心功能說明（對應 FAQ）與關鍵程式碼，並添加詳細註解，涵蓋多租戶架構、廣告管理、個人化推薦、報表生成和部署流程。

### 1. 什麼是 AdStackX？它的核心功能是什麼？

**答**：AdStackX 是一個多租戶廣告平台，基於 Laravel（後端）、FastAPI（推薦引擎）和 Vue 3（前端），整合 MySQL、Redis 和 Kafka，提供廣告管理、個人化推薦和即時報表功能。核心功能包括：
- 多租戶數據隔離
- 廣告創建與管理
- 個人化推薦
- 報表生成與視覺化
- 自動化部署與監控

### 2. 多租戶架構如何實現？如何保證數據隔離？

**答**：使用 Laravel 的自定義中間件（`SetTenant`）檢查 `X-Tenant-Id` 標頭，並透過全局範圍（`TenantScope`）在查詢中自動添加 `tenant_id` 過濾，確保數據隔離。

**程式碼**：`laravel/app/Http/Middleware/SetTenant.php`
```php
<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;

class SetTenant
{
    // 處理請求，設置當前租戶 ID
    public function handle(Request $request, Closure $next)
    {
        // 從請求標頭或用戶資料獲取租戶 ID
        $tenantId = $request->header('X-Tenant-Id') ?? auth()->user()->tenant_id;
        
        // 驗證租戶 ID 是否有效且存在
        if (!$tenantId || !Tenant::where('id', $tenantId)->exists()) {
            return response()->json(['error' => '無效或缺少租戶 ID'], 403);
        }
        
        // 確保已認證用戶的租戶 ID 與請求匹配
        if (auth()->check() && auth()->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => '租戶 ID 不匹配'], 403);
        }
        
        // 設置全域租戶 ID 配置
        config(['current_tenant_id' => $tenantId]);
        return $next($request);
    }
}
```

**程式碼**：`laravel/app/Models/Ad.php`
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Ad
{
    use LogsActivity;
    
    // 可填充字段，包含廣告屬性和租戶 ID
    protected $fillable = ['name', 'content', 'start_time', 'end_time', 'tenant_id'];
    
    // 記錄日誌的屬性
    protected static $logAttributes = ['name', 'content', 'start_time', 'end_time'];
    
    // 啟動時添加租戶範圍，自動過濾 tenant_id
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('tenant', function ($builder) {
            $builder->where('tenant_id', config('current_tenant_id'));
        });
    }
}
```

### 3. 廣告管理流程如何實現？如何控制權限？

**答**：廣告管理透過 `AdController` 實現創建、查詢等功能，使用 Spatie 的 RBAC 套件定義角色（`advertiser`、`viewer`）和權限（`create ads`、`view reports`），並透過 `Spatie\Activitylog` 記錄操作。

**程式碼**：`laravel/app/Http/Controllers/AdController.php`
```php
<?php
namespace App\Http\Controllers;
use App\Models\Ad;
use Illuminate\Http\Request;

class AdController extends Controller
{
    // 創建廣告
    public function store(Request $request)
    {
        // 檢查用戶是否有創建廣告的權限
        $this->authorize('create', Ad::class);
        
        try {
            // 驗證請求數據
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'content' => 'required|string',
                'start_time' => 'required|date',
                'end_time' => 'required|date|after:start_time',
            ]) + ['tenant_id' => config('current_tenant_id')];
            
            // 創建廣告記錄
            $ad = Ad::create($data);
            
            // 記錄創建日誌
            activity()
                ->performedOn($ad)
                ->causedBy(auth()->user())
                ->event('created')
                ->log('廣告 ' . $ad->name . ' 已創建。');
            
            // 觸發廣告創建事件
            event(new \App\Events\AdCreated($ad));
            
            // 增加 Prometheus 計數器
            \Prometheus\Counter::get('ads_created_total')->inc(['tenant_id' => tenancy()->tenant->id]);
            
            // 返回成功響應
            return response()->json(['message' => '廣告創建成功', 'ad' => $ad], 201);
        } catch (\Exception $e) {
            // 返回錯誤響應
            return response()->json(['error' => '創建廣告失敗: ' . $e->getMessage()], 400);
        }
    }

    // 查詢廣告列表
    public function index()
    {
        // 自動過濾當前租戶的廣告
        $ads = Ad::where('tenant_id', config('current_tenant_id'))->get();
        return response()->json($ads);
    }
}
```

### 4. 如何實現個人化推薦？

**答**：FastAPI 透過 Kafka 消費事件（點擊、展示），結合 Redis 快取和 MySQL 數據，生成推薦，支援簡單規則或未來整合機器學習模型。

**程式碼**：`fastapi/main.py`
```python
from fastapi import FastAPI, HTTPException, Request
from pydantic import BaseModel
import aiokafka
import redis.asyncio as redis
import mysql.connector
from slowapi import Limiter
from slowapi.util import get_remote_address
import json

# 初始化 FastAPI 應用和速率限制器
app = FastAPI()
limiter = Limiter(key_func=get_remote_address)

# 定義事件數據模型
class Event(BaseModel):
    tenant_id: int
    event_type: str
    data: dict

# 定義推薦請求數據模型
class RecommendationRequest(BaseModel):
    user_id: int
    tenant_id: int

# 健康檢查端點
@app.get("/health")
async def health():
    return {"status": "ok"}

# 記錄事件到 Kafka
@app.post("/event")
@limiter.limit("100/minute")
async def log_event(event: Event, request: Request):
    # 初始化 Kafka 生產者
    producer = aiokafka.AIOKafkaProducer(bootstrap_servers='kafka1:9092')
    await producer.start()
    try:
        # 發送事件到 Kafka 的 events 主題
        await producer.send_and_wait("events", event.json().encode())
        return {"status": "event logged"}
    finally:
        # 確保關閉生產者
        await producer.stop()

# 生成個人化推薦
@app.post("/recommend")
@limiter.limit("50/minute")
async def recommend(req: RecommendationRequest, request: Request):
    try:
        # 連接到 Redis 快取
        r = redis.Redis(host='redis', port=6379, decode_responses=True)
        cache_key = f"recommend:{req.tenant_id}:{req.user_id}"
        
        # 檢查快取是否存在
        cached = await r.get(cache_key)
        if cached:
            return {"recommendations": json.loads(cached)}
        
        # 連接到 MySQL 資料庫
        conn = mysql.connector.connect(
            host="mysql", database="ad_platform_db", user="user", password="secret"
        )
        cursor = conn.cursor()
        
        # 查詢用戶的點擊事件，獲取最近 5 個廣告
        cursor.execute(
            "SELECT ad_id FROM events WHERE tenant_id = %s AND user_id = %s AND event_type = 'click' LIMIT 5",
            (req.tenant_id, req.user_id)
        )
        ads = [row[0] for row in cursor.fetchall()]
        conn.close()
        
        # 生成簡單推薦結果
        recommendations = [{"ad_id": ad_id, "score": 1.0} for ad_id in ads]
        
        # 將結果快取到 Redis，設置 1 小時過期
        await r.setex(cache_key, 3600, json.dumps(recommendations))
        return {"recommendations": recommendations}
    except Exception as e:
        # 處理異常並返回錯誤
        raise HTTPException(status_code=500, detail=str(e))
```

### 5. 報表功能如何實現？

**答**：報表透過 `ReportController` 提供轉換率和事件統計 API，從 `events` 表按 `tenant_id` 聚合數據，結合 Vue.js 前端使用 Chart.js 展示。

**程式碼**：`laravel/app/Http/Controllers/ReportController.php`
```php
<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // 查詢轉換率報表
    public function conversions(Request $request)
    {
        // 檢查用戶是否有查看報表的權限
        $this->authorize('view reports', \App\Models\Ad::class);
        
        // 獲取當前租戶 ID
        $tenantId = config('current_tenant_id');
        
        // 查詢轉換事件並按廣告 ID 聚合
        $conversions = DB::table('events')
            ->where('tenant_id', $tenantId)
            ->where('event_type', 'conversion')
            ->selectRaw('ad_id, COUNT(*) as count')
            ->groupBy('ad_id')
            ->get();
        
        // 返回 JSON 響應
        return response()->json(['conversions' => $conversions]);
    }

    // 查詢事件統計報表
    public function events(Request $request)
    {
        // 檢查用戶是否有查看報表的權限
        $this->authorize('view reports', \App\Models\Ad::class);
        
        // 獲取當前租戶 ID
        $tenantId = config('current_tenant_id');
        
        // 查詢所有事件並按事件類型聚合
        $events = DB::table('events')
            ->where('tenant_id', $tenantId)
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->get();
        
        // 返回 JSON 響應
        return response()->json(['events' => $events]);
    }
}
```

**程式碼**：`frontend/src/components/ReportDashboard.vue`
```vue
<template>
  <div>
    <h2>報表儀表板</h2>
    <canvas id="conversionsChart"></canvas>
  </div>
</template>
<script>
import Chart from 'chart.js/auto';
import axios from 'axios';

export default {
  async mounted() {
    try {
      // 發送 API 請求獲取轉換率數據
      const response = await axios.get('http://ad-api.localhost/api/reports/conversions', {
        headers: { 'X-Tenant-Id': '1' }
      });
      
      // 提取轉換率數據
      const conversions = response.data.conversions;
      
      // 獲取畫布元素
      const ctx = document.getElementById('conversionsChart').getContext('2d');
      
      // 使用 Chart.js 繪製柱狀圖
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: conversions.map(c => `廣告 ${c.ad_id}`),
          datasets: [{
            label: '轉換次數',
            data: conversions.map(c => c.count),
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
          }]
        },
        options: { scales: { y: { beginAtZero: true } } }
      });
    } catch (error) {
      // 處理 API 請求錯誤
      console.error('獲取報表失敗:', error);
    }
  }
};
</script>
```

### 6. Laravel、FastAPI 和 Vue 3 如何協同工作？

**答**：Vue 3 透過 REST API 與 FastAPI 和 Laravel 通訊，FastAPI 處理推薦邏輯並與 Kafka 交互，Laravel 提供核心業務邏輯和數據儲存。

**程式碼**：`frontend/src/components/AdList.vue`
```vue
<template>
  <div>
    <h2>廣告列表</h2>
    <ul>
      <li v-for="ad in ads" :key="ad.id">
        {{ ad.name }} ({{ ad.start_time }} - {{ ad.end_time }})
      </li>
    </ul>
  </div>
</template>
<script>
import axios from 'axios';

export default {
  data() {
    return {
      ads: [] // 儲存廣告列表
    };
  },
  async mounted() {
    try {
      // 發送 API 請求獲取廣告列表
      const response = await axios.get('http://ad-api.localhost/api/ads', {
        headers: { 'X-Tenant-Id': '1' } // 添加租戶 ID 標頭
      });
      this.ads = response.data; // 更新廣告列表
    } catch (error) {
      // 處理 API 請求錯誤
      console.error('獲取廣告失敗:', error);
    }
  }
};
</script>
```

### 7. 如何確保 API 安全性和速率限制？

**答**：Laravel 使用 Sanctum 進行認證，FastAPI 使用 JWT，Spatie 套件實現 RBAC，速率限制由 Laravel 的 `throttle:api` 和 FastAPI 的 `slowapi` 處理，Traefik 整合 Let's Encrypt 提供 HTTPS。

### 8. CI/CD 流程如何運作？

**答**：GitHub Actions 運行測試（PHPUnit、Pytest、Jest），Trivy 掃描 Docker 映像漏洞，映像推送至容器註冊表，支援本地或雲端部署。

## 使用方法

- **前端訪問**：`http://frontend.localhost`
- **API 閘道**：`http://recommender.localhost/docs`
- **Laravel 後端**：`http://ad-api.localhost/api`
- **Prometheus UI**：`http://localhost:9090`
- **Grafana UI**：`http://localhost:3000`（用戶：admin，密碼：admin）

**運行測試**：
- **Laravel**：`docker-compose exec laravel vendor/bin/phpunit`
- **FastAPI**：`docker-compose exec fastapi pytest`
- **Vue 3**：`docker-compose exec frontend npm test`

## 部署

1. **構建並推送 Docker 映像**：
   ```bash
   docker build -t your_registry/adstackx-laravel:latest ./laravel
   docker build -t your_registry/adstackx-fastapi:latest ./fastapi
   docker build -t your_registry/adstackx-frontend:latest ./frontend
   docker push your_registry/adstackx-laravel:latest
   docker push your_registry/adstackx-fastapi:latest
   docker push your_registry/adstackx-frontend:latest
   ```

2. **應用 Docker Compose 配置**：
   ```bash
   docker-compose up --build -d
   ```

## 貢獻

歡迎提交 Pull Request 或 Issue！步驟：
1. Fork 倉庫。
2. 創建特性分支（`git checkout -b feature/YourFeature`）。
3. 提交更改（`git commit -m 'Add YourFeature'`）。
4. 推送分支（`git push origin feature/YourFeature`）。
5. 創建 Pull Request。

## 授權

採用 MIT 授權，詳見 [LICENSE](LICENSE) 文件。
