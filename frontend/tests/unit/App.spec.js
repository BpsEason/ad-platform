import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import App from '@/App.vue';
import axios from 'axios';

// Mock axios to prevent actual API calls during tests
vi.mock('axios');

describe('App.vue', () => {
  // Clear localStorage and mocks before each test
  beforeEach(() => {
    localStorage.clear();
    axios.post.mockReset();
    axios.get.mockReset();
    vi.spyOn(console, 'error').mockImplementation(() => {}); // Suppress console errors from tests
    vi.spyOn(console, 'warn').mockImplementation(() => {}); // Suppress console warnings from tests
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('renders loading spinner initially', () => {
    const wrapper = mount(App);
    expect(wrapper.find('.loading-spinner').exists()).toBe(true);
    expect(wrapper.text()).toContain('正在載入報表數據...');
  });

  it('fetches and displays data on mount', async () => {
    // Mock successful login and report data fetches
    axios.post.mockResolvedValueOnce({ data: { token: 'mock-token' } });
    axios.get.mockResolvedValueOnce({ data: { '2025-07-01': { impression: 100, click: 10 } } });
    axios.get.mockResolvedValueOnce({ data: { impressions: 100, clicks: 10, conversion_rate: '10.00%' } });

    const wrapper = mount(App);

    // Wait for async operations to complete
    await vi.runAllTimersAsync(); // If using timers for retries, ensure they run

    // Check if loading spinner is gone
    expect(wrapper.find('.loading-spinner').exists()).toBe(false);
    // Check if conversion rate is displayed
    expect(wrapper.text()).toContain('轉換率: 10.00%');
    // Check if chart data is present (indirectly by text content or component presence)
    expect(wrapper.text()).toContain('Chart.js 報表');
    expect(wrapper.text()).toContain('Apache ECharts 報表');
    expect(wrapper.find('.cache-status.data-loaded').exists()).toBe(true);
  });

  it('displays error message on API failure', async () => {
    axios.post.mockRejectedValueOnce(new Error('Network Error')); // Simulate login failure

    const wrapper = mount(App);
    await vi.runAllTimersAsync();

    expect(wrapper.find('.loading-spinner').exists()).toBe(false);
    expect(wrapper.find('.error-message').exists()).toBe(true);
    expect(wrapper.text()).toContain('錯誤: Network Error');
    expect(wrapper.find('.cache-status').text()).toContain('載入失敗，無法從伺服器獲取數據。');
  });

  it('loads data from localStorage cache', async () => {
    // Populate localStorage before mounting
    const mockCachedData = {
      report: { '2025-06-01': { impression: 50, click: 5 } },
      conversion: { impressions: 50, clicks: 5, conversion_rate: '10.00%' }
    };
    localStorage.setItem('reportData-2', JSON.stringify(mockCachedData));

    const wrapper = mount(App);
    // No need to mock axios calls as it should hit cache first
    await vi.runAllTimersAsync();

    expect(wrapper.find('.loading-spinner').exists()).toBe(false);
    expect(wrapper.text()).toContain('轉換率: 10.00%');
    expect(wrapper.find('.cache-status.cache-loaded').exists()).toBe(true);
    expect(wrapper.find('.cache-status').text()).toContain('數據從快取載入');
    // Ensure axios was NOT called for data fetching
    expect(axios.post).not.toHaveBeenCalled();
    expect(axios.get).not.toHaveBeenCalled();
  });

  it('re-fetches data when tenant changes', async () => {
    // Initial fetch
    axios.post.mockResolvedValueOnce({ data: { token: 'mock-token-initial' } });
    axios.get.mockResolvedValueOnce({ data: { '2025-07-01': { impression: 100, click: 10 } } });
    axios.get.mockResolvedValueOnce({ data: { impressions: 100, clicks: 10, conversion_rate: '10.00%' } });

    const wrapper = mount(App);
    await vi.runAllTimersAsync(); // Wait for initial fetch

    // Change tenant - this should trigger new fetches
    axios.post.mockResolvedValueOnce({ data: { token: 'mock-token-new-tenant' } });
    axios.get.mockResolvedValueOnce({ data: { '2025-07-02': { impression: 200, click: 20 } } });
    axios.get.mockResolvedValueOnce({ data: { impressions: 200, clicks: 20, conversion_rate: '10.00%' } });

    await wrapper.find('#tenant-select').setValue('1'); // Change to Tenant A
    await vi.runAllTimersAsync(); // Wait for new fetch

    expect(wrapper.text()).toContain('轉換率: 10.00%'); // Should reflect new data
    expect(wrapper.find('.cache-status.data-loaded').exists()).toBe(true); // New data loaded
    expect(axios.post).toHaveBeenCalledTimes(2); // Initial + tenant change
    expect(axios.get).toHaveBeenCalledTimes(4); // Initial (2) + tenant change (2)
  });
});
