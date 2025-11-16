import React, { useState, useEffect } from 'react'
import {
  Container,
  Typography,
  Box,
  Paper,
  TextField,
  Button,
  Grid,
  FormControlLabel,
  Switch,
  Divider,
  Alert,
  Autocomplete,
  Tabs,
  Tab,
} from '@mui/material'
import { Save, Person, Notifications, LocationOn } from '@mui/icons-material'
import axios from 'axios'

export default function UserSettingsPage() {
  const [tab, setTab] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [success, setSuccess] = useState(null)

  const [profile, setProfile] = useState({
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
  })

  const [preferences, setPreferences] = useState({
    defaultCostCenter: null,
    defaultDeliveryAddress: '',
    defaultLocationCode: '',
    defaultPhone: '',
    defaultAttentionTo: '',
  })

  const [notificationSettings, setNotificationSettings] = useState({
    emailNotifications: true,
    approvalRequired: true,
    requisitionApproved: true,
    requisitionRejected: true,
    invoiceApproved: true,
    invoiceRejected: true,
    goodsReceived: true,
    delegateAssigned: true,
  })

  const [chartOfAccounts, setChartOfAccounts] = useState([])

  useEffect(() => {
    loadSettings()
    loadChartOfAccounts()
  }, [])

  const loadSettings = async () => {
    try {
      const response = await axios.get('/api/users/me/settings')
      setProfile(response.data.profile || {})
      setPreferences(response.data.preferences || {})
      setNotificationSettings(response.data.notificationSettings || {})
      setLoading(false)
    } catch (err) {
      setError('Failed to load settings')
      setLoading(false)
    }
  }

  const loadChartOfAccounts = async () => {
    try {
      const response = await axios.get('/api/chart-of-accounts')
      setChartOfAccounts(response.data.accounts || [])
    } catch (err) {
      console.error('Failed to load chart of accounts:', err)
    }
  }

  const handleSaveProfile = async () => {
    try {
      await axios.put('/api/users/me/profile', profile)
      setSuccess('Profile updated successfully')
    } catch (err) {
      setError('Failed to update profile')
    }
  }

  const handleSavePreferences = async () => {
    try {
      await axios.put('/api/users/me/preferences', preferences)
      setSuccess('Preferences updated successfully')
    } catch (err) {
      setError('Failed to update preferences')
    }
  }

  const handleSaveNotifications = async () => {
    try {
      await axios.put('/api/users/me/notification-settings', notificationSettings)
      setSuccess('Notification settings updated successfully')
    } catch (err) {
      setError('Failed to update notification settings')
    }
  }

  if (loading) {
    return (
      <Container maxWidth="md" sx={{ mt: 4, textAlign: 'center' }}>
        <Typography>Loading...</Typography>
      </Container>
    )
  }

  return (
    <Container maxWidth="md" sx={{ mt: 4, mb: 4 }}>
      <Typography variant="h4" gutterBottom>
        Settings
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

      <Tabs value={tab} onChange={(e, val) => setTab(val)} sx={{ mb: 3 }}>
        <Tab icon={<Person />} label="Profile" />
        <Tab icon={<LocationOn />} label="Preferences" />
        <Tab icon={<Notifications />} label="Notifications" />
      </Tabs>

      {tab === 0 && (
        <Paper sx={{ p: 3 }}>
          <Typography variant="h6" gutterBottom>
            Profile Information
          </Typography>

          <Grid container spacing={2}>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                label="First Name"
                value={profile.firstName}
                onChange={(e) => setProfile({ ...profile, firstName: e.target.value })}
              />
            </Grid>

            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                label="Last Name"
                value={profile.lastName}
                onChange={(e) => setProfile({ ...profile, lastName: e.target.value })}
              />
            </Grid>

            <Grid item xs={12}>
              <TextField
                fullWidth
                label="Email"
                type="email"
                value={profile.email}
                onChange={(e) => setProfile({ ...profile, email: e.target.value })}
                helperText="Used for login and notifications"
              />
            </Grid>

            <Grid item xs={12}>
              <TextField
                fullWidth
                label="Phone"
                value={profile.phone}
                onChange={(e) => setProfile({ ...profile, phone: e.target.value })}
              />
            </Grid>
          </Grid>

          <Box mt={3}>
            <Button
              variant="contained"
              startIcon={<Save />}
              onClick={handleSaveProfile}
            >
              Save Profile
            </Button>
          </Box>
        </Paper>
      )}

      {tab === 1 && (
        <Paper sx={{ p: 3 }}>
          <Typography variant="h6" gutterBottom>
            Default Preferences
          </Typography>

          <Typography variant="body2" color="text.secondary" paragraph>
            These defaults will be pre-filled when creating new requisitions to save you time.
          </Typography>

          <Grid container spacing={2}>
            <Grid item xs={12}>
              <Autocomplete
                options={chartOfAccounts}
                getOptionLabel={(option) => `${option.fullCode} - ${option.fullName}`}
                value={preferences.defaultCostCenter}
                onChange={(e, value) =>
                  setPreferences({ ...preferences, defaultCostCenter: value })
                }
                renderInput={(params) => (
                  <TextField
                    {...params}
                    label="Default Cost Center"
                    helperText="Your preferred cost center for requisitions"
                  />
                )}
              />
            </Grid>

            <Grid item xs={12}>
              <Divider sx={{ my: 2 }} />
              <Typography variant="subtitle1" gutterBottom>
                Default Delivery Information
              </Typography>
            </Grid>

            <Grid item xs={12}>
              <TextField
                fullWidth
                label="Default Delivery Address"
                multiline
                rows={3}
                value={preferences.defaultDeliveryAddress}
                onChange={(e) =>
                  setPreferences({ ...preferences, defaultDeliveryAddress: e.target.value })
                }
                helperText="Your usual delivery address"
              />
            </Grid>

            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                label="Default Location Code"
                value={preferences.defaultLocationCode}
                onChange={(e) =>
                  setPreferences({ ...preferences, defaultLocationCode: e.target.value })
                }
              />
            </Grid>

            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                label="Default Phone"
                value={preferences.defaultPhone}
                onChange={(e) =>
                  setPreferences({ ...preferences, defaultPhone: e.target.value })
                }
              />
            </Grid>

            <Grid item xs={12}>
              <TextField
                fullWidth
                label="Default Attention To"
                value={preferences.defaultAttentionTo}
                onChange={(e) =>
                  setPreferences({ ...preferences, defaultAttentionTo: e.target.value })
                }
                helperText="Name of person to receive deliveries"
              />
            </Grid>
          </Grid>

          <Box mt={3}>
            <Button
              variant="contained"
              startIcon={<Save />}
              onClick={handleSavePreferences}
            >
              Save Preferences
            </Button>
          </Box>
        </Paper>
      )}

      {tab === 2 && (
        <Paper sx={{ p: 3 }}>
          <Typography variant="h6" gutterBottom>
            Notification Settings
          </Typography>

          <Typography variant="body2" color="text.secondary" paragraph>
            Choose which notifications you want to receive via email.
          </Typography>

          <Box>
            <FormControlLabel
              control={
                <Switch
                  checked={notificationSettings.emailNotifications}
                  onChange={(e) =>
                    setNotificationSettings({
                      ...notificationSettings,
                      emailNotifications: e.target.checked,
                    })
                  }
                />
              }
              label="Enable Email Notifications"
            />

            {notificationSettings.emailNotifications && (
              <Box ml={4} mt={2}>
                <Typography variant="subtitle2" gutterBottom>
                  Notify me when:
                </Typography>

                <FormControlLabel
                  control={
                    <Switch
                      checked={notificationSettings.approvalRequired}
                      onChange={(e) =>
                        setNotificationSettings({
                          ...notificationSettings,
                          approvalRequired: e.target.checked,
                        })
                      }
                    />
                  }
                  label="A requisition or invoice requires my approval"
                />

                <FormControlLabel
                  control={
                    <Switch
                      checked={notificationSettings.requisitionApproved}
                      onChange={(e) =>
                        setNotificationSettings({
                          ...notificationSettings,
                          requisitionApproved: e.target.checked,
                        })
                      }
                    />
                  }
                  label="My requisition is approved"
                />

                <FormControlLabel
                  control={
                    <Switch
                      checked={notificationSettings.requisitionRejected}
                      onChange={(e) =>
                        setNotificationSettings({
                          ...notificationSettings,
                          requisitionRejected: e.target.checked,
                        })
                      }
                    />
                  }
                  label="My requisition is rejected"
                />

                <FormControlLabel
                  control={
                    <Switch
                      checked={notificationSettings.invoiceApproved}
                      onChange={(e) =>
                        setNotificationSettings({
                          ...notificationSettings,
                          invoiceApproved: e.target.checked,
                        })
                      }
                    />
                  }
                  label="My invoice is approved"
                />

                <FormControlLabel
                  control={
                    <Switch
                      checked={notificationSettings.invoiceRejected}
                      onChange={(e) =>
                        setNotificationSettings({
                          ...notificationSettings,
                          invoiceRejected: e.target.checked,
                        })
                      }
                    />
                  }
                  label="My invoice is rejected"
                />

                <FormControlLabel
                  control={
                    <Switch
                      checked={notificationSettings.goodsReceived}
                      onChange={(e) =>
                        setNotificationSettings({
                          ...notificationSettings,
                          goodsReceived: e.target.checked,
                        })
                      }
                    />
                  }
                  label="Goods are received for my requisition"
                />

                <FormControlLabel
                  control={
                    <Switch
                      checked={notificationSettings.delegateAssigned}
                      onChange={(e) =>
                        setNotificationSettings({
                          ...notificationSettings,
                          delegateAssigned: e.target.checked,
                        })
                      }
                    />
                  }
                  label="I'm assigned as someone's delegate"
                />
              </Box>
            )}
          </Box>

          <Box mt={3}>
            <Button
              variant="contained"
              startIcon={<Save />}
              onClick={handleSaveNotifications}
            >
              Save Notification Settings
            </Button>
          </Box>
        </Paper>
      )}
    </Container>
  )
}
