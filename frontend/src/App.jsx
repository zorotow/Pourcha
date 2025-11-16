import { Routes, Route } from 'react-router-dom'
import { Container } from '@mui/material'
import BillingPage from './pages/BillingPage'
import HomePage from './pages/HomePage'
import LoginPage from './pages/LoginPage'

function App() {
  return (
    <Container maxWidth="lg">
      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/settings/billing" element={<BillingPage />} />
      </Routes>
    </Container>
  )
}

export default App
