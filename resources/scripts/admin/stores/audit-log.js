import axios from 'axios'
import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'

export const useAuditLogStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'auditLog',
    state: () => ({
      auditLogs: [],
      totalAuditLogs: 0,
      isFetching: false,
      currentAuditLog: null,
    }),

    getters: {
      getAuditLogs: (state) => state.auditLogs,
      getTotalAuditLogs: (state) => state.totalAuditLogs,
      getIsFetching: (state) => state.isFetching,
      getCurrentAuditLog: (state) => state.currentAuditLog,
    },

    actions: {
      async fetchAuditLogs(params = {}) {
        this.isFetching = true
        try {
          const response = await axios.get('/api/v1/audit-logs', { params })
          this.auditLogs = response.data.data
          this.totalAuditLogs = response.data.meta.total
          this.isFetching = false
          return response
        } catch (err) {
          this.isFetching = false
          handleError(err)
          throw err
        }
      },

      async fetchAuditLog(id) {
        try {
          const response = await axios.get(`/api/v1/audit-logs/${id}`)
          this.currentAuditLog = response.data.data
          return response
        } catch (err) {
          handleError(err)
          throw err
        }
      },

      clearAuditLogs() {
        this.auditLogs = []
        this.totalAuditLogs = 0
        this.currentAuditLog = null
      },
    },
  })()
} 