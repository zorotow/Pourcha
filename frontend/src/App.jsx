import { Routes, Route } from 'react-router-dom'
import { AppBar, Toolbar, Typography, Button, Box, Badge, IconButton, Menu, MenuItem } from '@mui/material'
import { ShoppingCart, Notifications, Settings, MoreVert } from '@mui/icons-material'
import { useNavigate } from 'react-router-dom'
import { useState } from 'react'
import HomePage from './pages/HomePage'
import LoginPage from './pages/LoginPage'
import BillingPage from './pages/BillingPage'
import DashboardPage from './pages/DashboardPage'
import CatalogPage from './pages/CatalogPage'
import CartPage from './pages/CartPage'
import RequisitionsPage from './pages/RequisitionsPage'
import InvoicesPage from './pages/InvoicesPage'
import ApprovalsPage from './pages/ApprovalsPage'
import GoodsReceiptPage from './pages/GoodsReceiptPage'
import IntegrationsPage from './pages/IntegrationsPage'
import UserSettingsPage from './pages/UserSettingsPage'

function App() {
  const navigate = useNavigate()
  const [settingsAnchor, setSettingsAnchor] = useState(null)

  const handleSettingsClick = (event) => {
    setSettingsAnchor(event.currentTarget)
  }

  const handleSettingsClose = () => {
    setSettingsAnchor(null)
  }

  const handleNavigateSettings = (path) => {
    navigate(path)
    handleSettingsClose()
  }

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
          <Button color="inherit" onClick={() => navigate('/goods-receipts')}>Receipts</Button>
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
          <IconButton color="inherit" onClick={handleSettingsClick}>
            <Settings />
          </IconButton>
          <Menu
            anchorEl={settingsAnchor}
            open={Boolean(settingsAnchor)}
            onClose={handleSettingsClose}
          >
            <MenuItem onClick={() => handleNavigateSettings('/settings/profile')}>
              User Settings
            </MenuItem>
            <MenuItem onClick={() => handleNavigateSettings('/settings/integrations')}>
              Integrations
            </MenuItem>
            <MenuItem onClick={() => handleNavigateSettings('/settings/billing')}>
              Billing
            </MenuItem>
          </Menu>
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
        <Route path="/goods-receipts" element={<GoodsReceiptPage />} />
        <Route path="/settings/profile" element={<UserSettingsPage />} />
        <Route path="/settings/integrations" element={<IntegrationsPage />} />
        <Route path="/settings/billing" element={<BillingPage />} />
      </Routes>
    </>
  )
}

export default App
