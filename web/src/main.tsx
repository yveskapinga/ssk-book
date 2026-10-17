import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { registerSW } from 'virtual:pwa-register'
import App from './App'
import { AuthProvider } from './auth/AuthContext'
import { InstallPrompt } from './components/InstallPrompt'
import { AppErrorProvider } from './lib/AppError'
import './styles.css'

registerSW({ immediate: true })

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: 1, throwOnError: false },
    mutations: { throwOnError: false },
  },
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <AppErrorProvider>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <AuthProvider>
            <InstallPrompt />
            <App />
          </AuthProvider>
        </BrowserRouter>
      </QueryClientProvider>
    </AppErrorProvider>
  </StrictMode>,
)
