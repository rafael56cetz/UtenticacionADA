export const ApiClient = {
  csrfToken: null,
  
  async getSession() {
    try {
      const res = await fetch('/api/session');
      if (res.ok) {
        const data = await res.json();
        if (data.data?.csrfToken) {
          this.csrfToken = data.data.csrfToken;
        }
        return data;
      }
      return null;
    } catch (err) {
      console.error('getSession error', err);
      return null;
    }
  },

  async request(endpoint, options = {}) {
    const headers = { ...options.headers };
    
    // Auto set content type if not FormData
    if (!(options.body instanceof FormData)) {
      headers['Content-Type'] = headers['Content-Type'] || 'application/json';
    }

    if (this.csrfToken && options.method && options.method.toUpperCase() !== 'GET') {
      headers['X-CSRF-Token'] = this.csrfToken;
    }

    const config = {
      ...options,
      headers,
      credentials: 'same-origin' // Ensure cookies are sent
    };

    // Serialize JSON body
    if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
      config.body = JSON.stringify(config.body);
    }

    try {
      const response = await fetch(endpoint, config);
      const isJson = response.headers.get('content-type')?.includes('application/json');
      const data = isJson ? await response.json() : null;
      
      // Update CSRF token if provided in response data (optional standard)
      if (data?.data?.csrfToken) {
        this.csrfToken = data.data.csrfToken;
      }

      if (!response.ok) {
        return { ok: false, error: data || { message: response.statusText }, status: response.status };
      }

      return { ok: true, data: data?.data, response };
    } catch (err) {
      console.error(`Network error on ${endpoint}`, err);
      return { ok: false, error: { message: 'Error de red o servicio no disponible.' }, status: 0 };
    }
  }
};
