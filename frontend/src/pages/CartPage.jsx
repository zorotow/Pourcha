import { useState, useEffect } from 'react'
import {
  Container,
  Typography,
  Box,
  Button,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  IconButton,
  TextField,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Tabs,
  Tab,
  FormControlLabel,
  Checkbox,
  List,
  ListItem,
  ListItemText,
  ListItemSecondaryAction,
  Chip,
  Autocomplete,
  Divider,
  Avatar,
} from '@mui/material'
import { Delete, ShoppingCart, Add, Person, Comment, History } from '@mui/icons-material'
import { useNavigate } from 'react-router-dom'
import axios from '../services/api'

function CartPage() {
  const navigate = useNavigate()
  const [cart, setCart] = useState(null)
  const [loading, setLoading] = useState(true)
  const [submitDialogOpen, setSubmitDialogOpen] = useState(false)
  const [tab, setTab] = useState(0)
  const [users, setUsers] = useState([])
  const [approvers, setApprovers] = useState([])
  const [comments, setComments] = useState([])
  const [newComment, setNewComment] = useState('')
  const [auditHistory, setAuditHistory] = useState([])

  useEffect(() => {
    loadCart()
    loadUsers()
    loadComments()
    loadHistory()
  }, [])

  const loadCart = async () => {
    try {
      const response = await axios.get('/cart')
      setCart(response.data)
      setApprovers(response.data.approvers || [])
    } catch (error) {
      console.error('Failed to load cart', error)
    } finally {
      setLoading(false)
    }
  }

  const loadUsers = async () => {
    try {
      const response = await axios.get('/users')
      setUsers(response.data.users || [])
    } catch (error) {
      console.error('Failed to load users', error)
    }
  }

  const loadComments = async () => {
    try {
      const response = await axios.get('/cart/comments')
      setComments(response.data.comments || [])
    } catch (error) {
      console.error('Failed to load comments', error)
    }
  }

  const loadHistory = async () => {
    try {
      const response = await axios.get('/cart/history')
      setAuditHistory(response.data.history || [])
    } catch (error) {
      console.error('Failed to load history', error)
    }
  }

  const handleUpdateQuantity = async (itemId, quantity) => {
    try {
      await axios.put(`/cart/items/${itemId}`, { quantity: parseInt(quantity) })
      loadCart()
    } catch (error) {
      alert('Failed to update quantity')
    }
  }

  const handleRemoveItem = async (itemId) => {
    try {
      await axios.delete(`/cart/items/${itemId}`)
      loadCart()
    } catch (error) {
      alert('Failed to remove item')
    }
  }

  const handleClearCart = async () => {
    if (confirm('Clear all items from cart?')) {
      try {
        await axios.post('/cart/clear')
        loadCart()
      } catch (error) {
        alert('Failed to clear cart')
      }
    }
  }

  const handleUpdateDetails = async (data) => {
    try {
      await axios.put('/cart/details', data)
      loadCart()
    } catch (error) {
      alert('Failed to update cart details')
    }
  }

  const handleAddApprover = async (user) => {
    if (!user) return

    try {
      await axios.post('/cart/approvers', { userId: user.id })
      setApprovers([...approvers, user])
    } catch (error) {
      alert('Failed to add approver')
    }
  }

  const handleRemoveApprover = async (userId) => {
    try {
      await axios.delete(`/cart/approvers/${userId}`)
      setApprovers(approvers.filter((a) => a.id !== userId))
    } catch (error) {
      alert('Failed to remove approver')
    }
  }

  const handleAddComment = async () => {
    if (!newComment.trim()) return

    try {
      const response = await axios.post('/cart/comments', { comment: newComment })
      setComments([response.data, ...comments])
      setNewComment('')
    } catch (error) {
      alert('Failed to add comment')
    }
  }

  const handleSubmitRequisition = async () => {
    try {
      await axios.post('/requisitions/from-cart', {
        cartId: cart.id,
        approvers: approvers.map((a) => a.id),
      })
      alert('Requisition created successfully!')
      navigate('/requisitions')
    } catch (error) {
      alert('Failed to create requisition')
    }
  }

  if (loading) return <Box sx={{ p: 3 }}>Loading...</Box>

  if (!cart || cart.items.length === 0) {
    return (
      <Container maxWidth="lg">
        <Box sx={{ mt: 4, textAlign: 'center' }}>
          <ShoppingCart sx={{ fontSize: 100, color: 'text.secondary' }} />
          <Typography variant="h5" sx={{ mt: 2 }}>
            Your cart is empty
          </Typography>
          <Button variant="contained" onClick={() => navigate('/catalog')} sx={{ mt: 2 }}>
            Browse Catalog
          </Button>
        </Box>
      </Container>
    )
  }

  return (
    <Container maxWidth="lg">
      <Box sx={{ mt: 4, mb: 4 }}>
        <Typography variant="h4" gutterBottom>
          Review Cart #{cart.id}
        </Typography>

        <Tabs value={tab} onChange={(e, val) => setTab(val)} sx={{ mb: 2 }}>
          <Tab label="Cart Items" />
          <Tab label="General Info" />
          <Tab label="Ship To" />
          <Tab label="Approvers" />
          <Tab label="Comments" />
          <Tab label="History" />
        </Tabs>

        {tab === 0 && (
          <>
            <TableContainer component={Paper}>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Description</TableCell>
                    <TableCell>Supplier</TableCell>
                    <TableCell>Unit Price</TableCell>
                    <TableCell>Quantity</TableCell>
                    <TableCell>Subtotal</TableCell>
                    <TableCell>Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {cart.items.map((item) => (
                    <TableRow key={item.id}>
                      <TableCell>{item.catalogItem.name}</TableCell>
                      <TableCell>{item.catalogItem.supplier.name}</TableCell>
                      <TableCell>${item.unitPrice}</TableCell>
                      <TableCell>
                        <TextField
                          type="number"
                          size="small"
                          value={item.quantity}
                          onChange={(e) => handleUpdateQuantity(item.id, e.target.value)}
                          inputProps={{ min: 1 }}
                          sx={{ width: 80 }}
                        />
                      </TableCell>
                      <TableCell>${item.subtotal.toFixed(2)}</TableCell>
                      <TableCell>
                        <IconButton onClick={() => handleRemoveItem(item.id)}>
                          <Delete />
                        </IconButton>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>

            <Box sx={{ mt: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <Box>
                <Button onClick={handleClearCart} color="error">
                  Clear Cart
                </Button>
              </Box>
              <Typography variant="h6">
                Total: ${cart.total.toFixed(2)} AUD
              </Typography>
            </Box>

            <Box sx={{ mt: 2, textAlign: 'right' }}>
              <Button
                variant="contained"
                size="large"
                onClick={() => setSubmitDialogOpen(true)}
              >
                Submit Requisition
              </Button>
            </Box>
          </>
        )}

        {tab === 1 && (
          <Paper sx={{ p: 3 }}>
            <TextField
              fullWidth
              label="Internal Note (not sent to supplier)"
              multiline
              rows={3}
              value={cart.internalNote || ''}
              onChange={(e) => handleUpdateDetails({ internalNote: e.target.value })}
              sx={{ mb: 2 }}
            />
            <TextField
              fullWidth
              label="Note to Supplier"
              multiline
              rows={3}
              value={cart.noteToSupplier || ''}
              onChange={(e) => handleUpdateDetails({ noteToSupplier: e.target.value })}
              sx={{ mb: 2 }}
            />
            <FormControlLabel
              control={
                <Checkbox
                  checked={cart.hidePrice}
                  onChange={(e) => handleUpdateDetails({ hidePrice: e.target.checked })}
                />
              }
              label="Hide Price"
            />
          </Paper>
        )}

        {tab === 2 && (
          <Paper sx={{ p: 3 }}>
            <TextField
              fullWidth
              label="Delivery Address"
              multiline
              rows={3}
              value={cart.deliveryAddress || ''}
              onChange={(e) => handleUpdateDetails({ deliveryAddress: e.target.value })}
              sx={{ mb: 2 }}
            />
            <TextField
              fullWidth
              label="Location Code"
              value={cart.locationCode || ''}
              onChange={(e) => handleUpdateDetails({ locationCode: e.target.value })}
              sx={{ mb: 2 }}
            />
            <TextField
              fullWidth
              label="Phone"
              value={cart.phone || ''}
              onChange={(e) => handleUpdateDetails({ phone: e.target.value })}
              sx={{ mb: 2 }}
            />
            <TextField
              fullWidth
              label="Attention To"
              value={cart.attentionTo || ''}
              onChange={(e) => handleUpdateDetails({ attentionTo: e.target.value })}
              sx={{ mb: 2 }}
            />
            <TextField
              fullWidth
              label="Special Delivery Instructions"
              multiline
              rows={3}
              value={cart.specialDeliveryInstructions || ''}
              onChange={(e) => handleUpdateDetails({ specialDeliveryInstructions: e.target.value })}
            />
          </Paper>
        )}

        {tab === 3 && (
          <Paper sx={{ p: 3 }}>
            <Typography variant="h6" gutterBottom>
              Approval Chain
            </Typography>
            <Typography variant="body2" color="text.secondary" paragraph>
              Select users who need to approve this requisition before it can be ordered.
            </Typography>

            <Autocomplete
              options={users.filter((u) => !approvers.find((a) => a.id === u.id))}
              getOptionLabel={(option) => `${option.firstName} ${option.lastName} (${option.email})`}
              renderInput={(params) => (
                <TextField {...params} label="Add Approver" placeholder="Search users..." />
              )}
              onChange={(e, value) => handleAddApprover(value)}
              sx={{ mb: 3 }}
            />

            {approvers.length === 0 ? (
              <Box sx={{ textAlign: 'center', py: 4, color: 'text.secondary' }}>
                <Person sx={{ fontSize: 60 }} />
                <Typography variant="body1" sx={{ mt: 2 }}>
                  No approvers added yet
                </Typography>
                <Typography variant="body2">
                  Add approvers to create an approval chain for this requisition
                </Typography>
              </Box>
            ) : (
              <List>
                {approvers.map((approver, index) => (
                  <Box key={approver.id}>
                    <ListItem>
                      <Avatar sx={{ mr: 2 }}>
                        {approver.firstName?.charAt(0)}
                        {approver.lastName?.charAt(0)}
                      </Avatar>
                      <ListItemText
                        primary={`${approver.firstName} ${approver.lastName}`}
                        secondary={approver.email}
                      />
                      <ListItemSecondaryAction>
                        <Chip label={`Step ${index + 1}`} size="small" sx={{ mr: 1 }} />
                        <IconButton
                          edge="end"
                          onClick={() => handleRemoveApprover(approver.id)}
                        >
                          <Delete />
                        </IconButton>
                      </ListItemSecondaryAction>
                    </ListItem>
                    {index < approvers.length - 1 && <Divider />}
                  </Box>
                ))}
              </List>
            )}
          </Paper>
        )}

        {tab === 4 && (
          <Paper sx={{ p: 3 }}>
            <Typography variant="h6" gutterBottom>
              Comments & Notes
            </Typography>

            <Box sx={{ mb: 3 }}>
              <TextField
                fullWidth
                multiline
                rows={3}
                placeholder="Add a comment..."
                value={newComment}
                onChange={(e) => setNewComment(e.target.value)}
                sx={{ mb: 1 }}
              />
              <Button
                variant="contained"
                startIcon={<Add />}
                onClick={handleAddComment}
                disabled={!newComment.trim()}
              >
                Add Comment
              </Button>
            </Box>

            {comments.length === 0 ? (
              <Box sx={{ textAlign: 'center', py: 4, color: 'text.secondary' }}>
                <Comment sx={{ fontSize: 60 }} />
                <Typography variant="body1" sx={{ mt: 2 }}>
                  No comments yet
                </Typography>
                <Typography variant="body2">
                  Add comments to communicate with approvers and track discussions
                </Typography>
              </Box>
            ) : (
              <List>
                {comments.map((comment, index) => (
                  <Box key={comment.id || index}>
                    <ListItem alignItems="flex-start">
                      <Avatar sx={{ mr: 2 }}>
                        {comment.user?.firstName?.charAt(0)}
                      </Avatar>
                      <ListItemText
                        primary={
                          <Box display="flex" alignItems="center" justifyContent="space-between">
                            <Typography variant="subtitle2">
                              {comment.user?.firstName} {comment.user?.lastName}
                            </Typography>
                            <Typography variant="caption" color="text.secondary">
                              {new Date(comment.createdAt).toLocaleString()}
                            </Typography>
                          </Box>
                        }
                        secondary={
                          <Typography variant="body2" sx={{ mt: 1 }}>
                            {comment.comment}
                          </Typography>
                        }
                      />
                    </ListItem>
                    {index < comments.length - 1 && <Divider variant="inset" component="li" />}
                  </Box>
                ))}
              </List>
            )}
          </Paper>
        )}

        {tab === 5 && (
          <Paper sx={{ p: 3 }}>
            <Typography variant="h6" gutterBottom>
              Cart History & Audit Trail
            </Typography>

            {auditHistory.length === 0 ? (
              <Box sx={{ textAlign: 'center', py: 4, color: 'text.secondary' }}>
                <History sx={{ fontSize: 60 }} />
                <Typography variant="body1" sx={{ mt: 2 }}>
                  No history available
                </Typography>
                <Typography variant="body2">
                  All changes to this cart will be tracked here
                </Typography>
              </Box>
            ) : (
              <List>
                {auditHistory.map((event, index) => (
                  <Box key={event.id || index}>
                    <ListItem alignItems="flex-start">
                      <Avatar sx={{ mr: 2, bgcolor: 'primary.main' }}>
                        <History />
                      </Avatar>
                      <ListItemText
                        primary={
                          <Box display="flex" alignItems="center" justifyContent="space-between">
                            <Typography variant="subtitle2">
                              {event.action}
                            </Typography>
                            <Typography variant="caption" color="text.secondary">
                              {new Date(event.timestamp).toLocaleString()}
                            </Typography>
                          </Box>
                        }
                        secondary={
                          <Box>
                            <Typography variant="body2" color="text.secondary">
                              {event.user?.firstName} {event.user?.lastName}
                            </Typography>
                            {event.details && (
                              <Typography variant="body2" sx={{ mt: 0.5 }}>
                                {event.details}
                              </Typography>
                            )}
                          </Box>
                        }
                      />
                    </ListItem>
                    {index < auditHistory.length - 1 && <Divider variant="inset" component="li" />}
                  </Box>
                ))}
              </List>
            )}
          </Paper>
        )}
      </Box>

      <Dialog open={submitDialogOpen} onClose={() => setSubmitDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Submit Requisition</DialogTitle>
        <DialogContent>
          <Typography>
            You are about to submit a requisition for ${cart.total.toFixed(2)} AUD with {cart.items.length} items.
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setSubmitDialogOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={handleSubmitRequisition}>
            Submit
          </Button>
        </DialogActions>
      </Dialog>
    </Container>
  )
}

export default CartPage
