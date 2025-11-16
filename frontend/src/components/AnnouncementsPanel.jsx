import React, { useState, useEffect } from 'react'
import {
  Paper,
  Typography,
  List,
  ListItem,
  ListItemText,
  Box,
  Chip,
  IconButton,
  Collapse,
  Divider,
  Alert,
} from '@mui/material'
import {
  Campaign,
  ExpandMore,
  ExpandLess,
  Info,
  Warning,
  CheckCircle,
  Error as ErrorIcon,
} from '@mui/icons-material'
import axios from 'axios'

export default function AnnouncementsPanel({ compact = false }) {
  const [announcements, setAnnouncements] = useState([])
  const [loading, setLoading] = useState(true)
  const [expanded, setExpanded] = useState(true)

  useEffect(() => {
    loadAnnouncements()
  }, [])

  const loadAnnouncements = async () => {
    try {
      const response = await axios.get('/api/announcements')
      setAnnouncements(response.data.announcements || [])
      setLoading(false)
    } catch (err) {
      console.error('Failed to load announcements:', err)
      setLoading(false)
    }
  }

  const getSeverityIcon = (severity) => {
    switch (severity) {
      case 'info':
        return <Info color="info" />
      case 'warning':
        return <Warning color="warning" />
      case 'success':
        return <CheckCircle color="success" />
      case 'error':
        return <ErrorIcon color="error" />
      default:
        return <Campaign />
    }
  }

  const getSeverityColor = (severity) => {
    switch (severity) {
      case 'info':
        return 'info'
      case 'warning':
        return 'warning'
      case 'success':
        return 'success'
      case 'error':
        return 'error'
      default:
        return 'default'
    }
  }

  if (loading || announcements.length === 0) {
    return null
  }

  const activeAnnouncements = announcements.filter((a) => a.active)

  if (compact) {
    return (
      <Paper elevation={2} sx={{ p: 2 }}>
        <Box display="flex" alignItems="center" justifyContent="space-between" mb={1}>
          <Box display="flex" alignItems="center">
            <Campaign color="primary" sx={{ mr: 1 }} />
            <Typography variant="h6">
              Announcements ({activeAnnouncements.length})
            </Typography>
          </Box>
          <IconButton size="small" onClick={() => setExpanded(!expanded)}>
            {expanded ? <ExpandLess /> : <ExpandMore />}
          </IconButton>
        </Box>

        <Collapse in={expanded}>
          {activeAnnouncements.slice(0, 3).map((announcement, index) => (
            <Alert
              key={announcement.id}
              severity={getSeverityColor(announcement.severity)}
              icon={getSeverityIcon(announcement.severity)}
              sx={{ mb: index < Math.min(3, activeAnnouncements.length) - 1 ? 1 : 0 }}
            >
              <Typography variant="subtitle2">
                {announcement.title}
              </Typography>
              <Typography variant="body2">
                {announcement.message}
              </Typography>
              <Typography variant="caption" color="text.secondary">
                {new Date(announcement.publishedAt).toLocaleDateString()}
              </Typography>
            </Alert>
          ))}

          {activeAnnouncements.length > 3 && (
            <Box mt={1}>
              <Typography variant="body2" color="primary">
                + {activeAnnouncements.length - 3} more announcements
              </Typography>
            </Box>
          )}
        </Collapse>
      </Paper>
    )
  }

  return (
    <Paper sx={{ p: 3 }}>
      <Box display="flex" alignItems="center" mb={2}>
        <Campaign color="primary" sx={{ mr: 1 }} />
        <Typography variant="h5">
          Procurement Announcements
        </Typography>
      </Box>

      {activeAnnouncements.length === 0 ? (
        <Box sx={{ textAlign: 'center', py: 4, color: 'text.secondary' }}>
          <Campaign sx={{ fontSize: 60 }} />
          <Typography variant="h6" sx={{ mt: 2 }}>
            No active announcements
          </Typography>
          <Typography variant="body2">
            Check back later for updates and procurement news.
          </Typography>
        </Box>
      ) : (
        <List>
          {activeAnnouncements.map((announcement, index) => (
            <React.Fragment key={announcement.id}>
              <ListItem alignItems="flex-start">
                <Box mr={2} mt={0.5}>
                  {getSeverityIcon(announcement.severity)}
                </Box>
                <ListItemText
                  primary={
                    <Box display="flex" alignItems="center" justifyContent="space-between">
                      <Typography variant="subtitle1">
                        {announcement.title}
                      </Typography>
                      {announcement.category && (
                        <Chip
                          label={announcement.category}
                          size="small"
                          variant="outlined"
                        />
                      )}
                    </Box>
                  }
                  secondary={
                    <>
                      <Typography
                        component="span"
                        variant="body2"
                        color="text.primary"
                        display="block"
                        sx={{ mt: 1 }}
                      >
                        {announcement.message}
                      </Typography>
                      {announcement.link && (
                        <Typography
                          component="a"
                          href={announcement.link}
                          variant="body2"
                          color="primary"
                          sx={{ mt: 0.5, display: 'block' }}
                        >
                          Learn more →
                        </Typography>
                      )}
                      <Typography
                        variant="caption"
                        color="text.secondary"
                        display="block"
                        sx={{ mt: 1 }}
                      >
                        Published: {new Date(announcement.publishedAt).toLocaleDateString()}
                        {announcement.expiresAt && (
                          <> • Expires: {new Date(announcement.expiresAt).toLocaleDateString()}</>
                        )}
                      </Typography>
                    </>
                  }
                />
              </ListItem>
              {index < activeAnnouncements.length - 1 && <Divider variant="inset" component="li" />}
            </React.Fragment>
          ))}
        </List>
      )}
    </Paper>
  )
}
