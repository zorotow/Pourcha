import { Routes, Route } from 'react-router-dom'
import { AppBar, Toolbar, Typography, Button, Box, Badge, IconButton } from '@mui/material'
import { ShoppingCart, Notifications } from '@mui/icons-material'
import { useNavigate } from 'react-router-dom'
import HomePage from './pages/HomePage'
import LoginPage from './pages/LoginPage'
import BillingPage from './pages/BillingPage'
import DashboardPage from './pages/DashboardPage'
import CatalogPage from './pages/CatalogPage'
import CartPage from './pages/CartPage'
import RequisitionsPage from './pages/RequisitionsPage'
import InvoicesPage from './pages/InvoicesPage'
import ApprovalsPage from './pages/ApprovalsPage'

function App() {
  const navigate = useNavigate()

  return (
    <>
      <AppBar position="static">
        <Toolbar>
          <Typography variant="h6" component="div" sx={{ flexGrow: 1, cursor: 'pointer' }} onClick={() => navigate('/dashboard')}>
            Pourcha
          </Typography>
          <Button color="inherit" onClick={() => navigate('/dashboard')}>Dashboard</Button>
          <Button color="inherit" onClick={() => navigate('/catalog')}>Catalog</Button>
          <Button color="inherit" onClick={() => navigate('/requisitions')}>Requisitions</Button>
          <Button color="inherit" onClick={() => navigate('/invoices')}>Invoices</Button>
          <Button color="inherit" onClick={() => navigate('/approvals')}>Approvals</Button>
          <IconButton color="inherit" onClick={() => navigate('/cart')}>
            <Badge badgeContent={0} color="error">
              <ShoppingCart />
            </Badge>
          </IconButton>
          <IconButton color="inherit">
            <Badge badgeContent={0} color="error">
              <Notifications />
            </Badge>
          </IconButton>
          <Button color="inherit" onClick={() => navigate('/settings/billing')}>Billing</Button>
        </Toolbar>
      </AppBar>

      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/catalog" element={<CatalogPage />} />
        <Route path="/cart" element={<CartPage />} />
        <Route path="/requisitions" element={<RequisitionsPage />} />
        <Route path="/invoices" element={<InvoicesPage />} />
        <Route path="/approvals" element={<ApprovalsPage />} />
        <Route path="/settings/billing" element={<BillingPage />} />
      </Routes>
    </>
  )
}

export default App
