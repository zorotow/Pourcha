import React, { useState, useEffect } from 'react'
import {
  Container,
  Typography,
  Box,
  Card,
  CardContent,
  CardActions,
  Button,
  Grid,
  Chip,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  Alert,
  CircularProgress,
  List,
  ListItem,
  ListItemText,
  ListItemIcon,
  Divider,
} from '@mui/material'
import {
  CheckCircle,
  Cancel,
  Error as ErrorIcon,
  Sync,
  Link as LinkIcon,
  LinkOff,
  Settings as SettingsIcon,
  Storage,
  AccountBalance,
  Business,
} from '@mui/icons-material'
import axios from 'axios'

const INTEGRATION_ICONS = {
  xero: <Business />,
  quickbooks: <Business />,
  sap: <Storage />,
  plaid: <AccountBalance />,
}

const INTEGRATION_NAMES = {
  xero: 'Xero',
  quickbooks: 'QuickBooks Online',
  sap: 'SAP',
  plaid: 'Plaid Bank Linking',
}

const INTEGRATION_DESCRIPTIONS = {
  xero: 'Sync suppliers, chart of accounts, invoices, and purchase orders with Xero',
  quickbooks: 'Sync vendors, accounts, bills, and purchase orders with QuickBooks Online',
  sap: 'Integrate with SAP ERP for business partners, GL accounts, and procurement documents',
  plaid: 'Link bank accounts for payment initiation and transaction reconciliation',
}

export default function IntegrationsPage() {
  const [integrations, setIntegrations] = useState([])
  const [loading, setLoading] = useState(true)
  const [syncing, setSyncing] = useState(null)
  const [connectDialog, setConnectDialog] = useState({ open: false, provider: null })
  const [sapConfigDialog, setSapConfigDialog] = useState({ open: false })
  const [sapConfig, setSapConfig] = useState({
    api_url: '',
    username: '',
    password: '',
  })
  const [error, setError] = useState(null)
  const [success, setSuccess] = useState(null)

  useEffect(() => {
    loadIntegrations()

    // Check for OAuth callback success/error
    const params = new URLSearchParams(window.location.search)
    if (params.get('success')) {
      setSuccess(`Successfully connected to ${params.get('provider')}`)
      window.history.replaceState({}, '', '/settings/integrations')
    }
    if (params.get('error')) {
      setError(`Failed to connect: ${params.get('error')}`)
      window.history.replaceState({}, '', '/settings/integrations')
    }
  }, [])

  const loadIntegrations = async () => {
    try {
      const response = await axios.get('/api/integrations')
      setIntegrations(response.data.integrations)
      setLoading(false)
    } catch (err) {
      setError('Failed to load integrations')
      setLoading(false)
    }
  }

  const handleConnect = async (provider) => {
    if (provider === 'sap') {
      setSapConfigDialog({ open: true })
      return
    }

    try {
      const response = await axios.post(`/api/integrations/${provider}/connect`)

      if (provider === 'plaid') {
        // For Plaid, open Link in popup/iframe
        // This is simplified - in production, use Plaid Link SDK
        window.location.href = response.data.auth_url
      } else {
        // For OAuth providers (Xero, QuickBooks)
        window.location.href = response.data.auth_url
      }
    } catch (err) {
      setError(`Failed to initiate connection: ${err.response?.data?.error || err.message}`)
    }
  }

  const handleSAPConnect = async () => {
    try {
      // SAP uses basic auth, no OAuth
      // Store config via integration settings endpoint
      await axios.post('/api/integrations/sap/configure', {
        config: sapConfig,
      })
      setSapConfigDialog({ open: false })
      setSuccess('SAP integration configured successfully')
      loadIntegrations()
    } catch (err) {
      setError(`Failed to configure SAP: ${err.response?.data?.error || err.message}`)
    }
  }

  const handleDisconnect = async (provider) => {
    if (!confirm(`Are you sure you want to disconnect from ${INTEGRATION_NAMES[provider]}?`)) {
      return
    }

    try {
      await axios.post(`/api/integrations/${provider}/disconnect`)
      setSuccess(`Disconnected from ${INTEGRATION_NAMES[provider]}`)
      loadIntegrations()
    } catch (err) {
      setError(`Failed to disconnect: ${err.response?.data?.error || err.message}`)
    }
  }

  const handleSync = async (provider) => {
    setSyncing(provider)
    setError(null)
    setSuccess(null)

    try {
      const response = await axios.post(`/api/integrations/${provider}/sync`)
      setSuccess(`Sync completed: ${response.data.message}`)
      loadIntegrations()
    } catch (err) {
      setError(`Sync failed: ${err.response?.data?.error || err.message}`)
    } finally {
      setSyncing(null)
    }
  }

  const handleTest = async (provider) => {
    try {
      await axios.post(`/api/integrations/${provider}/test`)
      setSuccess('Connection test successful')
    } catch (err) {
      setError(`Connection test failed: ${err.response?.data?.message || err.message}`)
    }
  }

  const getStatusColor = (status) => {
    switch (status) {
      case 'connected':
        return 'success'
      case 'error':
        return 'error'
      default:
        return 'default'
    }
  }

  const getStatusIcon = (status) => {
    switch (status) {
      case 'connected':
        return <CheckCircle />
      case 'error':
        return <ErrorIcon />
      default:
        return <Cancel />
    }
  }

  if (loading) {
    return (
      <Container maxWidth="lg" sx={{ mt: 4, textAlign: 'center' }}>
        <CircularProgress />
      </Container>
    )
  }

  return (
    <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
      <Typography variant="h4" gutterBottom>
        External Integrations
      </Typography>

      <Typography variant="body1" color="text.secondary" paragraph>
        Connect Pourcha with your existing accounting and banking systems for seamless data synchronization.
      </Typography>

      {error && (
        <Alert severity="error" onClose={() => setError(null)} sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      {success && (
        <Alert severity="success" onClose={() => setSuccess(null)} sx={{ mb: 2 }}>
          {success}
        </Alert>
      )}

      <Grid container spacing={3}>
        {integrations.map((integration) => (
          <Grid item xs={12} md={6} key={integration.provider}>
            <Card>
              <CardContent>
                <Box display="flex" alignItems="center" mb={2}>
                  <Box mr={2}>
                    {INTEGRATION_ICONS[integration.provider]}
                  </Box>
                  <Box flexGrow={1}>
                    <Typography variant="h6">
                      {INTEGRATION_NAMES[integration.provider]}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      {INTEGRATION_DESCRIPTIONS[integration.provider]}
                    </Typography>
                  </Box>
                  <Chip
                    icon={getStatusIcon(integration.status)}
                    label={integration.status}
                    color={getStatusColor(integration.status)}
                    size="small"
                  />
                </Box>

                {integration.status === 'connected' && (
                  <Box>
                    <Divider sx={{ my: 2 }} />
                    <List dense>
                      <ListItem>
                        <ListItemText
                          primary="Connected"
                          secondary={new Date(integration.connected_at).toLocaleString()}
                        />
                      </ListItem>
                      {integration.last_sync_at && (
                        <ListItem>
                          <ListItemText
                            primary="Last Synced"
                            secondary={new Date(integration.last_sync_at).toLocaleString()}
                          />
                        </ListItem>
                      )}
                    </List>
                  </Box>
                )}
              </CardContent>

              <CardActions>
                {integration.status === 'connected' ? (
                  <>
                    <Button
                      size="small"
                      startIcon={syncing === integration.provider ? <CircularProgress size={16} /> : <Sync />}
                      onClick={() => handleSync(integration.provider)}
                      disabled={syncing === integration.provider}
                    >
                      Sync Now
                    </Button>
                    <Button
                      size="small"
                      startIcon={<SettingsIcon />}
                      onClick={() => handleTest(integration.provider)}
                    >
                      Test
                    </Button>
                    <Button
                      size="small"
                      color="error"
                      startIcon={<LinkOff />}
                      onClick={() => handleDisconnect(integration.provider)}
                    >
                      Disconnect
                    </Button>
                  </>
                ) : (
                  <Button
                    size="small"
                    variant="contained"
                    startIcon={<LinkIcon />}
                    onClick={() => handleConnect(integration.provider)}
                  >
                    Connect
                  </Button>
                )}
              </CardActions>
            </Card>
          </Grid>
        ))}
      </Grid>

      {/* SAP Configuration Dialog */}
      <Dialog
        open={sapConfigDialog.open}
        onClose={() => setSapConfigDialog({ open: false })}
        maxWidth="sm"
        fullWidth
      >
        <DialogTitle>Configure SAP Integration</DialogTitle>
        <DialogContent>
          <Typography variant="body2" color="text.secondary" paragraph>
            Enter your SAP system details. Your credentials will be stored securely.
          </Typography>

          <TextField
            fullWidth
            label="SAP API URL"
            placeholder="https://your-sap-server.com/sap/opu/odata"
            value={sapConfig.api_url}
            onChange={(e) => setSapConfig({ ...sapConfig, api_url: e.target.value })}
            margin="normal"
            helperText="The base URL of your SAP OData API"
          />

          <TextField
            fullWidth
            label="Username"
            value={sapConfig.username}
            onChange={(e) => setSapConfig({ ...sapConfig, username: e.target.value })}
            margin="normal"
          />

          <TextField
            fullWidth
            label="Password"
            type="password"
            value={sapConfig.password}
            onChange={(e) => setSapConfig({ ...sapConfig, password: e.target.value })}
            margin="normal"
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setSapConfigDialog({ open: false })}>
            Cancel
          </Button>
          <Button
            variant="contained"
            onClick={handleSAPConnect}
            disabled={!sapConfig.api_url || !sapConfig.username || !sapConfig.password}
          >
            Connect
          </Button>
        </DialogActions>
      </Dialog>

      <Box mt={4}>
        <Alert severity="info">
          <Typography variant="body2">
            <strong>Sync Behavior:</strong> When you sync an integration, Pourcha will pull suppliers and
            chart of accounts from the external system. Any requisitions and invoices created in Pourcha
            can be pushed to the external system using the export buttons on their respective pages.
          </Typography>
        </Alert>
      </Box>
    </Container>
  )
}
