<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import Chart from 'chart.js/auto';
import * as echarts from 'echarts';
import axios from 'axios';

const chartJsCanvasRef = ref(null);
const echartsDivRef = ref(null);
const reportData = ref(null);
const conversionRate = ref(null);
const error = ref(null);
const loading = ref(true);
const selectedTenant = ref('2'); // Default to Tenant B for demo
const cacheStatus = ref('');

const availableTenants = [
  { id: '1', name: 'Tenant A' },
  { id: '2', name: 'Tenant B' },
  { id: '3', name: 'Tenant C' },
  { id: '4', name: 'Tenant D' },
  { id: '5', name: 'Tenant E' },
];

let chartJsInstance = null;
let echartsInstance = null;

const fetchDataWithRetry = async (url, options, retries = 3, delay = 1000) => {
  for (let i = 0; i < retries; i++) {
    try {
      const response = await axios(url, options);
      return response.data;
    } catch (err) {
      if (i < retries - 1) {
        console.warn(`Attempt ${i + 1} failed. Retrying in ${delay / 1000}s...`, err);
        await new Promise(res => setTimeout(res, delay));
        delay *= 2; // Exponential backoff
      } else {
        throw err;
      }
    }
  }
};

const fetchReportData = async () => {
  loading.value = true;
  error.value = null;
  cacheStatus.value = '';

  const cacheKey = `reportData-${selectedTenant.value}`;
  const cachedData = localStorage.getItem(cacheKey);

  if (cachedData) {
    try {
      const parsedData = JSON.parse(cachedData);
      reportData.value = parsedData.report;
      conversionRate.value = parsedData.conversion;
      cacheStatus.value = '數據從快取載入 (可能不是最新)';
      loading.value = false;
      renderCharts(); // Render immediately from cache
      return;
    } catch (e) {
      console.error('解析快取數據失敗:', e);
      localStorage.removeItem(cacheKey); // Clear invalid cache
    }
  }

  try {
    // 1. Simulating login to get a token
    const loginResponse = await fetchDataWithRetry('http://ad-api.localhost/api/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      data: { email: 'viewerB@example.com', password: 'password' }
    });
    const token = loginResponse.token;
    if (!token) {
      throw new Error('Failed to get token after login.');
    }
    console.log('Successfully obtained token:', token);

    const authHeaders = {
      'Authorization': `Bearer ${token}`,
      'X-Tenant-Id': selectedTenant.value
    };

    // 2. Fetch events data
    const eventsData = await fetchDataWithRetry('http://ad-api.localhost/api/reports/events', {
      headers: authHeaders
    });
    reportData.value = eventsData;

    // 3. Fetch conversion rate
    const conversionData = await fetchDataWithRetry('http://ad-api.localhost/api/reports/conversions', {
      headers: authHeaders
    });
    conversionRate.value = conversionData;

    // Cache the fetched data
    localStorage.setItem(cacheKey, JSON.stringify({ report: eventsData, conversion: conversionData }));
    cacheStatus.value = '數據已成功從伺服器載入並快取';

  } catch (err) {
    console.error('Error fetching report data:', err);
    error.value = err.response?.data?.message || err.response?.data?.error || err.message;
    cacheStatus.value = '載入失敗，無法從伺服器獲取數據。';
  } finally {
    loading.value = false;
  }
};

const renderCharts = () => {
  if (!reportData.value) return;

  const dates = Object.keys(reportData.value).sort();
  const impressions = dates.map(date => reportData.value[date]['impression'] || 0);
  const clicks = dates.map(date => reportData.value[date]['click'] || 0);

  // Chart.js
  if (chartJsCanvasRef.value) {
    const ctx = chartJsCanvasRef.value.getContext('2d');
    if (chartJsInstance) {
      chartJsInstance.destroy();
    }
    chartJsInstance = new Chart(ctx, {
      type: 'line',
      data: {
        labels: dates,
        datasets: [{
          label: '曝光 (Impressions)',
          data: impressions,
          borderColor: 'rgb(75, 192, 192)',
          tension: 0.1,
          fill: false
        }, {
          label: '點擊 (Clicks)',
          data: clicks,
          borderColor: 'rgb(255, 99, 132)',
          tension: 0.1,
          fill: false
        }]
      },
      options: { scales: { y: { beginAtZero: true } } }
    });
  }

  // ECharts
  if (echartsDivRef.value) {
    if (echartsInstance) {
      echartsInstance.dispose(); // Dispose existing instance
    }
    echartsInstance = echarts.init(echartsDivRef.value);
    const option = {
      title: { text: '每日曝光與點擊事件' },
      tooltip: { trigger: 'axis' },
      legend: { data: ['曝光 (Impressions)', '點擊 (Clicks)'] },
      xAxis: { type: 'category', data: dates },
      yAxis: { type: 'value' },
      series: [
        { name: '曝光 (Impressions)', type: 'line', data: impressions, smooth: true },
        { name: '點擊 (Clicks)', type: 'line', data: clicks, smooth: true }
      ]
    };
    echartsInstance.setOption(option);
  }
};

onMounted(() => {
  fetchReportData();
});

watch([reportData, selectedTenant], () => {
  renderCharts();
});

// Watch for selectedTenant changes to re-fetch data
watch(selectedTenant, () => {
  fetchReportData();
});

onBeforeUnmount(() => {
  if (chartJsInstance) {
    chartJsInstance.destroy();
  }
  if (echartsInstance) {
    echartsInstance.dispose();
  }
});
</script>

<template>
  <div class="dashboard-container">
    <h1>廣告報表儀表板 (Vue 3 App)</h1>

    <div class="tenant-selector-wrapper">
      <label for="tenant-select">選擇租戶:</label>
      <select
        id="tenant-select"
        v-model="selectedTenant"
        class="tenant-select"
      >
        <option v-for="tenant in availableTenants" :key="tenant.id" :value="tenant.id">
          {{ tenant.name }} (ID: {{ tenant.id }})
        </option>
      </select>
    </div>

    <div v-if="loading" class="loading-spinner">
      <div class="spinner"></div>
      <p>正在載入報表數據...</p>
    </div>

    <div v-if="error" class="error-message">
      錯誤: {{ error }}
    </div>

    <div v-if="cacheStatus" :class="['cache-status', { 'cache-loaded': cacheStatus.includes('快取載入'), 'data-loaded': cacheStatus.includes('伺服器載入') }]">
      {{ cacheStatus }}
    </div>

    <div v-if="!loading && !error && conversionRate" class="conversion-rate-box">
      轉換率: <strong>{{ conversionRate.conversion_rate }}</strong> ({{ conversionRate.clicks }} 點擊 / {{ conversionRate.impressions }} 曝光)
    </div>
    
    <div v-if="!loading && !error && reportData" class="charts-container">
      <div class="chart-card">
        <h2>Chart.js 報表</h2>
        <canvas ref="chartJsCanvasRef"></canvas>
      </div>
      <div class="chart-card">
        <h2>Apache ECharts 報表</h2>
        <div ref="echartsDivRef" class="echarts-chart-area"></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dashboard-container {
  font-family: 'Arial', sans-serif;
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
  background-color: #f8f9fa;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

h1 {
  text-align: center;
  color: #333;
  margin-bottom: 30px;
}

.tenant-selector-wrapper {
  margin-bottom: 25px;
  text-align: center;
}

.tenant-selector-wrapper label {
  margin-right: 10px;
  font-weight: bold;
  color: #555;
}

.tenant-select {
  padding: 10px 15px;
  border: 1px solid #ccc;
  border-radius: 8px;
  background-color: #fff;
  font-size: 1em;
  box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
  transition: border-color 0.3s ease;
}

.tenant-select:focus {
  border-color: #007bff;
  outline: none;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
}

.loading-spinner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 200px;
  color: #666;
}

.spinner {
  border: 4px solid rgba(0, 0, 0, 0.1);
  border-left-color: #007bff;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
  margin-bottom: 10px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-message {
  color: #dc3545;
  background-color: #f8d7da;
  border: 1px solid #f5c6cb;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
  text-align: center;
  font-weight: bold;
}

.cache-status {
  padding: 10px;
  border-radius: 5px;
  margin-bottom: 20px;
  text-align: center;
  font-size: 0.9em;
  color: #495057;
  background-color: #e9ecef;
  border: 1px solid #dee2e6;
}

.cache-status.cache-loaded {
  background-color: #fff3cd;
  border-color: #ffeeba;
  color: #856404;
}

.cache-status.data-loaded {
  background-color: #d4edda;
  border-color: #c3e6cb;
  color: #155724;
}

.conversion-rate-box {
  background-color: #e9f7ef;
  border: 1px solid #cce5d4;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 30px;
  text-align: center;
  font-size: 1.3em;
  color: #28a745;
  font-weight: bold;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.charts-container {
  display: grid;
  grid-template-columns: 1fr;
  gap: 40px;
}

@media (min-width: 768px) {
  .charts-container {
    grid-template-columns: 1fr 1fr;
  }
}

.chart-card {
  background-color: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  display: flex;
  flex-direction: column;
  align-items: center;
}

.chart-card h2 {
  color: #444;
  margin-top: 0;
  margin-bottom: 20px;
  font-size: 1.5em;
}

.echarts-chart-area {
  width: 100%;
  height: 300px; /* Fixed height for ECharts */
}
</style>
