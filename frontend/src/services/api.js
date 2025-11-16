import axios from 'axios';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add auth token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle 401 responses
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export const authService = {
  login: async (email, password) => {
    const response = await api.post('/login_check', { email, password });
    const { token } = response.data;
    localStorage.setItem('token', token);
    return response.data;
  },

  logout: () => {
    localStorage.removeItem('token');
  },

  isAuthenticated: () => {
    return !!localStorage.getItem('token');
  },
};

export const subscriptionService = {
  getSubscription: async () => {
    const response = await api.get('/subscription');
    return response.data;
  },

  cancelSubscription: async () => {
    const response = await api.post('/subscription/cancel');
    return response.data;
  },

  getPaymentHistory: async () => {
    const response = await api.get('/subscription/history');
    return response.data;
  },
};

export const checkoutService = {
  getPlans: async () => {
    const response = await api.get('/checkout/plans');
    return response.data;
  },

  createCheckout: async (plan, gateway, interval = 'monthly', cryptoCurrency = null) => {
    const response = await api.post(`/checkout/${plan}`, {
      gateway,
      interval,
      crypto_currency: cryptoCurrency,
    });
    return response.data;
  },

  getCryptoWallets: async () => {
    const response = await api.get('/checkout/crypto/wallets');
    return response.data;
  },

  getCryptoPaymentDetails: async (paymentId) => {
    const response = await api.get(`/checkout/crypto/payment/${paymentId}`);
    return response.data;
  },
};

export const orderService = {
  createOrder: async (plan, gateway, amount) => {
    const response = await api.post('/orders', { plan, gateway, amount });
    return response.data;
  },

  simulatePayment: async (orderId, success = true) => {
    const response = await api.post('/orders/simulate-payment', {
      order_id: orderId,
      success,
    });
    return response.data;
  },
};

export default api;
