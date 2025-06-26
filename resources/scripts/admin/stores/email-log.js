import axios from 'axios'
import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'

export const useEmailLogStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'emailLog',
    state: () => ({
      emailLogs: [],
      totalEmailLogs: 0,
      isFetching: false,
      currentEmailLog: null,
    }),

    getters: {
      getEmailLogs: (state) => state.emailLogs,
      getTotalEmailLogs: (state) => state.totalEmailLogs,
      getIsFetching: (state) => state.isFetching,
      getCurrentEmailLog: (state) => state.currentEmailLog,
    },

    actions: {
      async fetchEmailLogs(params = {}) {
        this.isFetching = true
        try {
          const response = await axios.get('/api/v1/email-logs', { params })
          this.emailLogs = response.data.data
          this.totalEmailLogs = response.data.meta.total
          this.isFetching = false
          return response
        } catch (err) {
          this.isFetching = false
          handleError(err)
          throw err
        }
      },

      async fetchEmailLog(id) {
        try {
          const response = await axios.get(`/api/v1/email-logs/${id}`)
          this.currentEmailLog = response.data.data
          return response
        } catch (err) {
          handleError(err)
          throw err
        }
      },

      clearEmailLogs() {
        this.emailLogs = []
        this.totalEmailLogs = 0
        this.currentEmailLog = null
      },
    },
  })()
} 