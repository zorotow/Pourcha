import React, { useState, useEffect } from 'react'
import {
  Container,
  Typography,
  Box,
  Paper,
  Button,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Chip,
  Alert,
  Grid,
  Card,
  CardContent,
  IconButton,
  Tooltip,
} from '@mui/material'
import {
  Add,
  CheckCircle,
  Visibility,
  Assignment,
  Warning,
} from '@mui/icons-material'
import axios from 'axios'

const CONDITION_COLORS = {
  good: 'success',
  damaged: 'warning',
  defective: 'error',
}

export default function GoodsReceiptPage() {
  const [receipts, setReceipts] = useState([])
  const [loading, setLoading] = useState(true)
  const [createDialog, setCreateDialog] = useState({ open: false, requisition: null })
  const [detailDialog, setDetailDialog] = useState({ open: false, receipt: null })
  const [requisitions, setRequisitions] = useState([])
  const [receiptData, setReceiptData] = useState({
    receipt_date: new Date().toISOString().split('T')[0],
    delivery_note_number: '',
    receiving_location: '',
    notes: '',
    items: {},
  })
  const [error, setError] = useState(null)
  const [success, setSuccess] = useState(null)

  useEffect(() => {
    loadReceipts()
    loadApprovedRequisitions()
  }, [])

  const loadReceipts = async () => {
    try {
      const response = await axios.get('/api/goods-receipts')
      setReceipts(response.data.receipts)
      setLoading(false)
    } catch (err) {
      setError('Failed to load goods receipts')
      setLoading(false)
    }
  }

  const loadApprovedRequisitions = async () => {
    try {
      const response = await axios.get('/api/requisitions?status=approved')
      setRequisitions(response.data.requisitions || [])
    } catch (err) {
      console.error('Failed to load requisitions:', err)
    }
  }

  const openCreateDialog = async (requisitionId) => {
    try {
      const response = await axios.get(`/api/requisitions/${requisitionId}`)
      const requisition = response.data

      // Initialize receipt items with ordered quantities
      const items = {}
      requisition.items.forEach((item) => {
        items[item.id] = {
          received_quantity: item.quantity,
          accepted_quantity: item.quantity,
          rejected_quantity: 0,
          condition: 'good',
          notes: '',
        }
      })

      setReceiptData({
        ...receiptData,
        items,
      })

      setCreateDialog({ open: true, requisition })
    } catch (err) {
      setError('Failed to load requisition details')
    }
  }

  const handleCreateReceipt = async () => {
    try {
      const response = await axios.post(
        `/api/goods-receipts/from-requisition/${createDialog.requisition.id}`,
        receiptData
      )

      setSuccess(`Goods receipt ${response.data.receipt_number} created successfully`)
      setCreateDialog({ open: false, requisition: null })
      loadReceipts()
    } catch (err) {
      setError(`Failed to create receipt: ${err.response?.data?.error || err.message}`)
    }
  }

  const handleUpdateItem = (itemId, field, value) => {
    setReceiptData({
      ...receiptData,
      items: {
        ...receiptData.items,
        [itemId]: {
          ...receiptData.items[itemId],
          [field]: value,
        },
      },
    })
  }

  const handleConfirmReceipt = async (receiptId) => {
    if (!confirm('Are you sure you want to confirm this goods receipt? This action cannot be undone.')) {
      return
    }

    try {
      await axios.post(`/api/goods-receipts/${receiptId}/confirm`)
      setSuccess('Goods receipt confirmed successfully')
      loadReceipts()
    } catch (err) {
      setError(`Failed to confirm receipt: ${err.response?.data?.error || err.message}`)
    }
  }

  const openDetailDialog = async (receiptId) => {
    try {
      const response = await axios.get(`/api/goods-receipts/${receiptId}`)
      setDetailDialog({ open: true, receipt: response.data })
    } catch (err) {
      setError('Failed to load receipt details')
    }
  }

  const getStatusColor = (status) => {
    switch (status) {
      case 'confirmed':
        return 'success'
      case 'draft':
        return 'default'
      case 'cancelled':
        return 'error'
      default:
        return 'default'
    }
  }

  return (
    <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4">Goods Receipts</Typography>
        <Button
          variant="contained"
          startIcon={<Add />}
          onClick={() => setCreateDialog({ open: true, requisition: null })}
        >
          Create Receipt
        </Button>
      </Box>

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

      <TableContainer component={Paper}>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Receipt Number</TableCell>
              <TableCell>Requisition</TableCell>
              <TableCell>Supplier</TableCell>
              <TableCell>Receipt Date</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Discrepancies</TableCell>
              <TableCell>Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {receipts.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} align="center">
                  No goods receipts found
                </TableCell>
              </TableRow>
            ) : (
              receipts.map((receipt) => (
                <TableRow key={receipt.id}>
                  <TableCell>{receipt.receipt_number}</TableCell>
                  <TableCell>{receipt.requisition_number}</TableCell>
                  <TableCell>{receipt.supplier.name}</TableCell>
                  <TableCell>{new Date(receipt.receipt_date).toLocaleDateString()}</TableCell>
                  <TableCell>
                    <Chip label={receipt.status} color={getStatusColor(receipt.status)} size="small" />
                  </TableCell>
                  <TableCell>
                    {receipt.has_discrepancies && (
                      <Chip
                        icon={<Warning />}
                        label="Has Discrepancies"
                        color="warning"
                        size="small"
                      />
                    )}
                  </TableCell>
                  <TableCell>
                    <Tooltip title="View Details">
                      <IconButton size="small" onClick={() => openDetailDialog(receipt.id)}>
                        <Visibility />
                      </IconButton>
                    </Tooltip>
                    {receipt.status === 'draft' && (
                      <Tooltip title="Confirm Receipt">
                        <IconButton
                          size="small"
                          color="success"
                          onClick={() => handleConfirmReceipt(receipt.id)}
                        >
                          <CheckCircle />
                        </IconButton>
                      </Tooltip>
                    )}
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>

      {/* Create Receipt Dialog - Step 1: Select Requisition */}
      <Dialog
        open={createDialog.open && !createDialog.requisition}
        onClose={() => setCreateDialog({ open: false, requisition: null })}
        maxWidth="sm"
        fullWidth
      >
        <DialogTitle>Create Goods Receipt</DialogTitle>
        <DialogContent>
          <Typography variant="body2" color="text.secondary" paragraph>
            Select an approved requisition to receive goods against:
          </Typography>

          <FormControl fullWidth sx={{ mt: 2 }}>
            <InputLabel>Requisition</InputLabel>
            <Select
              label="Requisition"
              onChange={(e) => openCreateDialog(e.target.value)}
            >
              {requisitions.map((req) => (
                <MenuItem key={req.id} value={req.id}>
                  {req.requisition_number} - {req.supplier?.name} - ${req.total_amount}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setCreateDialog({ open: false, requisition: null })}>
            Cancel
          </Button>
        </DialogActions>
      </Dialog>

      {/* Create Receipt Dialog - Step 2: Enter Receipt Details */}
      <Dialog
        open={createDialog.open && !!createDialog.requisition}
        onClose={() => setCreateDialog({ open: false, requisition: null })}
        maxWidth="lg"
        fullWidth
      >
        <DialogTitle>
          Create Goods Receipt for {createDialog.requisition?.requisition_number}
        </DialogTitle>
        <DialogContent>
          <Grid container spacing={2} sx={{ mt: 1 }}>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                label="Receipt Date"
                type="date"
                value={receiptData.receipt_date}
                onChange={(e) => setReceiptData({ ...receiptData, receipt_date: e.target.value })}
                InputLabelProps={{ shrink: true }}
              />
            </Grid>
            <Grid item xs={12} sm={6}>
              <TextField
                fullWidth
                label="Delivery Note Number"
                value={receiptData.delivery_note_number}
                onChange={(e) =>
                  setReceiptData({ ...receiptData, delivery_note_number: e.target.value })
                }
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                label="Receiving Location"
                value={receiptData.receiving_location}
                onChange={(e) =>
                  setReceiptData({ ...receiptData, receiving_location: e.target.value })
                }
              />
            </Grid>
            <Grid item xs={12}>
              <TextField
                fullWidth
                multiline
                rows={2}
                label="Notes"
                value={receiptData.notes}
                onChange={(e) => setReceiptData({ ...receiptData, notes: e.target.value })}
              />
            </Grid>
          </Grid>

          <Typography variant="h6" sx={{ mt: 3, mb: 2 }}>
            Receipt Items
          </Typography>

          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Description</TableCell>
                  <TableCell>Ordered</TableCell>
                  <TableCell>Received</TableCell>
                  <TableCell>Accepted</TableCell>
                  <TableCell>Rejected</TableCell>
                  <TableCell>Condition</TableCell>
                  <TableCell>Notes</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {createDialog.requisition?.items?.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>{item.description}</TableCell>
                    <TableCell>{item.quantity}</TableCell>
                    <TableCell>
                      <TextField
                        type="number"
                        size="small"
                        value={receiptData.items[item.id]?.received_quantity || 0}
                        onChange={(e) =>
                          handleUpdateItem(item.id, 'received_quantity', e.target.value)
                        }
                        sx={{ width: 80 }}
                      />
                    </TableCell>
                    <TableCell>
                      <TextField
                        type="number"
                        size="small"
                        value={receiptData.items[item.id]?.accepted_quantity || 0}
                        onChange={(e) =>
                          handleUpdateItem(item.id, 'accepted_quantity', e.target.value)
                        }
                        sx={{ width: 80 }}
                      />
                    </TableCell>
                    <TableCell>
                      <TextField
                        type="number"
                        size="small"
                        value={receiptData.items[item.id]?.rejected_quantity || 0}
                        onChange={(e) =>
                          handleUpdateItem(item.id, 'rejected_quantity', e.target.value)
                        }
                        sx={{ width: 80 }}
                      />
                    </TableCell>
                    <TableCell>
                      <Select
                        size="small"
                        value={receiptData.items[item.id]?.condition || 'good'}
                        onChange={(e) => handleUpdateItem(item.id, 'condition', e.target.value)}
                        sx={{ width: 120 }}
                      >
                        <MenuItem value="good">Good</MenuItem>
                        <MenuItem value="damaged">Damaged</MenuItem>
                        <MenuItem value="defective">Defective</MenuItem>
                      </Select>
                    </TableCell>
                    <TableCell>
                      <TextField
                        size="small"
                        value={receiptData.items[item.id]?.notes || ''}
                        onChange={(e) => handleUpdateItem(item.id, 'notes', e.target.value)}
                        sx={{ width: 150 }}
                      />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setCreateDialog({ open: false, requisition: null })}>
            Cancel
          </Button>
          <Button variant="contained" onClick={handleCreateReceipt}>
            Create Receipt
          </Button>
        </DialogActions>
      </Dialog>

      {/* View Receipt Detail Dialog */}
      <Dialog
        open={detailDialog.open}
        onClose={() => setDetailDialog({ open: false, receipt: null })}
        maxWidth="md"
        fullWidth
      >
        <DialogTitle>
          Goods Receipt {detailDialog.receipt?.receipt_number}
        </DialogTitle>
        <DialogContent>
          {detailDialog.receipt && (
            <>
              <Grid container spacing={2} sx={{ mb: 3 }}>
                <Grid item xs={12} sm={6}>
                  <Typography variant="subtitle2" color="text.secondary">
                    Requisition
                  </Typography>
                  <Typography>{detailDialog.receipt.requisition.number}</Typography>
                </Grid>
                <Grid item xs={12} sm={6}>
                  <Typography variant="subtitle2" color="text.secondary">
                    Supplier
                  </Typography>
                  <Typography>{detailDialog.receipt.supplier.name}</Typography>
                </Grid>
                <Grid item xs={12} sm={6}>
                  <Typography variant="subtitle2" color="text.secondary">
                    Receipt Date
                  </Typography>
                  <Typography>
                    {new Date(detailDialog.receipt.receipt_date).toLocaleDateString()}
                  </Typography>
                </Grid>
                <Grid item xs={12} sm={6}>
                  <Typography variant="subtitle2" color="text.secondary">
                    Status
                  </Typography>
                  <Chip
                    label={detailDialog.receipt.status}
                    color={getStatusColor(detailDialog.receipt.status)}
                    size="small"
                  />
                </Grid>
                {detailDialog.receipt.delivery_note_number && (
                  <Grid item xs={12} sm={6}>
                    <Typography variant="subtitle2" color="text.secondary">
                      Delivery Note
                    </Typography>
                    <Typography>{detailDialog.receipt.delivery_note_number}</Typography>
                  </Grid>
                )}
                {detailDialog.receipt.receiving_location && (
                  <Grid item xs={12} sm={6}>
                    <Typography variant="subtitle2" color="text.secondary">
                      Location
                    </Typography>
                    <Typography>{detailDialog.receipt.receiving_location}</Typography>
                  </Grid>
                )}
              </Grid>

              {detailDialog.receipt.has_discrepancies && (
                <Alert severity="warning" sx={{ mb: 2 }}>
                  This receipt has discrepancies between ordered and received quantities.
                </Alert>
              )}

              <Typography variant="h6" gutterBottom>
                Items
              </Typography>

              <TableContainer>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Description</TableCell>
                      <TableCell>Ordered</TableCell>
                      <TableCell>Received</TableCell>
                      <TableCell>Accepted</TableCell>
                      <TableCell>Rejected</TableCell>
                      <TableCell>Condition</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {detailDialog.receipt.items.map((item) => (
                      <TableRow key={item.id}>
                        <TableCell>{item.description}</TableCell>
                        <TableCell>{item.ordered_quantity}</TableCell>
                        <TableCell>{item.received_quantity}</TableCell>
                        <TableCell>{item.accepted_quantity}</TableCell>
                        <TableCell>{item.rejected_quantity}</TableCell>
                        <TableCell>
                          <Chip
                            label={item.condition}
                            color={CONDITION_COLORS[item.condition]}
                            size="small"
                          />
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>

              {detailDialog.receipt.notes && (
                <Box mt={2}>
                  <Typography variant="subtitle2" color="text.secondary">
                    Notes
                  </Typography>
                  <Typography>{detailDialog.receipt.notes}</Typography>
                </Box>
              )}
            </>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDetailDialog({ open: false, receipt: null })}>
            Close
          </Button>
        </DialogActions>
      </Dialog>
    </Container>
  )
}
