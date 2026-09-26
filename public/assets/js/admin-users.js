import { ApiClient } from './api-client.js';

export const AdminUsers = {
  async listUsers(page = 1) {
    return ApiClient.request(`/api/users?page=${page}`, { method: 'GET' });
  },

  async getUser(id) {
    return ApiClient.request(`/api/users/${id}`, { method: 'GET' });
  },

  async createUser(data) {
    return ApiClient.request('/api/users', {
      method: 'POST',
      body: data
    });
  },

  async updateUser(id, data) {
    return ApiClient.request(`/api/users/${id}`, {
      method: 'PATCH',
      body: data
    });
  },

  async toggleStatus(id, active) {
    return ApiClient.request(`/api/users/${id}/status`, {
      method: 'PATCH',
      body: { active }
    });
  },

  async beginEnroll(id, method) {
    return ApiClient.request(`/api/users/${id}/enroll/${method}/begin`, {
      method: 'POST'
    });
  },

  async finishEnroll(id, method, payload, samples = []) {
    const formData = new FormData();
    formData.append('payload', JSON.stringify(payload));
    
    samples.forEach((blob, index) => {
      const ext = method === 'face' ? 'jpg' : (blob.type.includes('webm') ? 'webm' : 'wav');
      formData.append('samples[]', blob, `sample_${index}.${ext}`);
    });

    return ApiClient.request(`/api/users/${id}/enroll/${method}/finish`, {
      method: 'POST',
      body: formData
    });
  },

  async revokeCredential(userId, credentialId) {
    return ApiClient.request(`/api/users/${userId}/credentials/${credentialId}`, {
      method: 'DELETE'
    });
  }
};
