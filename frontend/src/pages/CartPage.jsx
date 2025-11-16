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
} from '@mui/material'
import { Delete, ShoppingCart } from '@mui/icons-material'
import { useNavigate } from 'react-router-dom'
import axios from '../services/api'

function CartPage() {
  const navigate = useNavigate()
  const [cart, setCart] = useState(null)
  const [loading, setLoading] = useState(true)
  const [submitDialogOpen, setSubmitDialogOpen] = useState(false)
  const [tab, setTab] = useState(0)

  useEffect(() => {
    loadCart()
  }, [])

  const loadCart = async () => {
    try {
      const response = await axios.get('/cart')
      setCart(response.data)
    } catch (error) {
      console.error('Failed to load cart', error)
    } finally {
      setLoading(false)
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

  const handleSubmitRequisition = async () => {
    try {
      await axios.post('/requisitions/from-cart', {
        cartId: cart.id,
        approvers: [], // Add approver selection logic
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
