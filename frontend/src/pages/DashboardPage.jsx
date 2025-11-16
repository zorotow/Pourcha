import { useState, useEffect } from 'react'
import {
  Container,
  Typography,
  Box,
  Grid,
  Card,
  CardContent,
  CardActions,
  Button,
  TextField,
  Dialog,
  DialogTitle,
  DialogContent,
  List,
  ListItem,
  ListItemText,
  Badge,
  IconButton,
  Chip,
} from '@mui/material'
import {
  ShoppingCart,
  Receipt,
  CheckCircle,
  Notifications,
  Store,
  Description,
} from '@mui/icons-material'
import { useNavigate } from 'react-router-dom'
import axios from '../services/api'
import ToDoList from '../components/ToDoList'
import AnnouncementsPanel from '../components/AnnouncementsPanel'

function DashboardPage() {
  const navigate = useNavigate()
  const [dashboard, setDashboard] = useState(null)
  const [loading, setLoading] = useState(true)
  const [guidedDialogOpen, setGuidedDialogOpen] = useState(true)
  const [search, setSearch] = useState('')

  useEffect(() => {
    loadDashboard()
  }, [])

  const loadDashboard = async () => {
    try {
      const response = await axios.get('/dashboard')
      setDashboard(response.data)
    } catch (error) {
      console.error('Failed to load dashboard', error)
    } finally {
      setLoading(false)
    }
  }

  const handleSearch = (e) => {
    e.preventDefault()
    if (search.trim()) {
      navigate(`/catalog?search=${encodeURIComponent(search)}`)
    }
  }

  if (loading) return <Box sx={{ p: 3 }}>Loading...</Box>

  return (
    <Container maxWidth="lg">
      <Box sx={{ mt: 4, mb: 4 }}>
        <Typography variant="h4" gutterBottom>
          Good {new Date().getHours() < 12 ? 'morning' : 'afternoon'}, {dashboard?.user?.firstName}!
        </Typography>

        {/* Search Bar */}
        <Box component="form" onSubmit={handleSearch} sx={{ my: 3 }}>
          <TextField
            fullWidth
            placeholder="What do you need? Start your search here"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            InputProps={{
              endAdornment: (
                <Button type="submit" variant="contained">
                  Search
                </Button>
              ),
            }}
          />
        </Box>

        {/* Quick Stats */}
        <Grid container spacing={2} sx={{ mb: 3 }}>
          <Grid item xs={12} md={3}>
            <Card>
              <CardContent>
                <Badge badgeContent={dashboard?.pending_approvals || 0} color="error">
                  <CheckCircle fontSize="large" color="primary" />
                </Badge>
                <Typography variant="h6">{dashboard?.pending_approvals || 0}</Typography>
                <Typography variant="body2" color="text.secondary">
                  Pending Approvals
                </Typography>
              </CardContent>
            </Card>
          </Grid>
          <Grid item xs={12} md={3}>
            <Card>
              <CardContent>
                <Badge badgeContent={dashboard?.unread_notifications || 0} color="error">
                  <Notifications fontSize="large" color="primary" />
                </Badge>
                <Typography variant="h6">{dashboard?.unread_notifications || 0}</Typography>
                <Typography variant="body2" color="text.secondary">
                  Notifications
                </Typography>
              </CardContent>
            </Card>
          </Grid>
        </Grid>

        {/* To-Do List and Announcements */}
        <Grid container spacing={2} sx={{ mb: 3 }}>
          <Grid item xs={12} md={6}>
            <ToDoList compact />
          </Grid>
          <Grid item xs={12} md={6}>
            <AnnouncementsPanel compact />
          </Grid>
        </Grid>

        {/* Popular Tasks */}
        <Typography variant="h6" gutterBottom sx={{ mt: 3 }}>
          Popular Tasks
        </Typography>
        <Grid container spacing={2} sx={{ mb: 3 }}>
          {dashboard?.popular_tasks?.slice(0, 12).map((task) => (
            <Grid item xs={6} sm={4} md={3} key={task.id}>
              <Card sx={{ cursor: 'pointer', '&:hover': { boxShadow: 3 } }} onClick={() => navigate(`/${task.id}`)}>
                <CardContent>
                  <Box sx={{ textAlign: 'center' }}>
                    {task.id === 'approve_invoices' && <CheckCircle fontSize="large" />}
                    {task.id === 'view_requisitions' && <Receipt fontSize="large" />}
                    {task.id === 'goods_request' && <ShoppingCart fontSize="large" />}
                    {task.id === 'submit_invoice' && <Description fontSize="large" />}
                    {task.id === 'view_suppliers' && <Store fontSize="large" />}
                    <Typography variant="body2" sx={{ mt: 1 }}>
                      {task.title}
                    </Typography>
                    {task.count > 0 && (
                      <Chip label={task.count} size="small" color="primary" sx={{ mt: 1 }} />
                    )}
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          ))}
        </Grid>

        {/* Additional Stores */}
        <Typography variant="h6" gutterBottom>
          Additional Stores
        </Typography>
        <Grid container spacing={2} sx={{ mb: 3 }}>
          {dashboard?.suppliers?.map((supplier) => (
            <Grid item xs={6} sm={4} md={2} key={supplier.id}>
              <Card
                sx={{ cursor: 'pointer', '&:hover': { boxShadow: 3 } }}
                onClick={() => navigate(`/catalog?supplier=${supplier.id}`)}
              >
                <CardContent sx={{ textAlign: 'center' }}>
                  {supplier.logoUrl ? (
                    <img src={supplier.logoUrl} alt={supplier.name} style={{ maxWidth: '100%', height: 60 }} />
                  ) : (
                    <Store fontSize="large" />
                  )}
                  <Typography variant="caption" display="block" sx={{ mt: 1 }}>
                    {supplier.name}
                  </Typography>
                </CardContent>
              </Card>
            </Grid>
          ))}
        </Grid>

        {/* Recent Requisitions */}
        {dashboard?.recent_requisitions?.length > 0 && (
          <>
            <Typography variant="h6" gutterBottom>
              Recent Requisitions
            </Typography>
            <Card sx={{ mb: 3 }}>
              <List>
                {dashboard.recent_requisitions.map((req) => (
                  <ListItem
                    key={req.id}
                    button
                    onClick={() => navigate(`/requisitions/${req.id}`)}
                  >
                    <ListItemText
                      primary={`Requisition ${req.requisitionNumber}`}
                      secondary={`${req.totalAmount} - ${req.status}`}
                    />
                    <Chip label={req.status} size="small" />
                  </ListItem>
                ))}
              </List>
            </Card>
          </>
        )}

        {/* Help Section */}
        <Typography variant="h6" gutterBottom>
          Help
        </Typography>
        <Box sx={{ mb: 3 }}>
          <Button sx={{ mr: 1 }}>Online Help Guides</Button>
          <Button sx={{ mr: 1 }}>Contact Support</Button>
          <Button>View Procurement Policy</Button>
        </Box>
      </Box>

      {/* Guided Dialog */}
      <Dialog open={guidedDialogOpen} onClose={() => setGuidedDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>What do you need today?</DialogTitle>
        <DialogContent>
          <List>
            <ListItem button onClick={() => { navigate('/catalog'); setGuidedDialogOpen(false); }}>
              <ListItemText primary="Make a Purchase" />
            </ListItem>
            <ListItem button onClick={() => { navigate('/invoices/create'); setGuidedDialogOpen(false); }}>
              <ListItemText primary="Submit an Invoice" />
            </ListItem>
            <ListItem button onClick={() => { navigate('/suppliers'); setGuidedDialogOpen(false); }}>
              <ListItemText primary="Create or find a Supplier" />
            </ListItem>
            <ListItem button onClick={() => { navigate('/support'); setGuidedDialogOpen(false); }}>
              <ListItemText primary="Contact Support" />
            </ListItem>
            <ListItem button onClick={() => { setGuidedDialogOpen(false); }}>
              <ListItemText primary="View Online Help Guides" />
            </ListItem>
          </List>
          <Box sx={{ mt: 2, textAlign: 'center' }}>
            <Button onClick={() => setGuidedDialogOpen(false)}>Skip to write a request</Button>
          </Box>
        </DialogContent>
      </Dialog>
    </Container>
  )
}

export default DashboardPage
