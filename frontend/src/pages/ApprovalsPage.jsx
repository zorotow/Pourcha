import { useState, useEffect } from 'react'
import {
  Container,
  Typography,
  Box,
  Paper,
  List,
  ListItem,
  ListItemText,
  Chip,
  Button,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  Tabs,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
} from '@mui/material'
import axios from '../services/api'

function ApprovalsPage() {
  const [pendingRequisitions, setPendingRequisitions] = useState([])
  const [pendingInvoices, setPendingInvoices] = useState([])
  const [selectedItem, setSelectedItem] = useState(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [comments, setComments] = useState('')
  const [tab, setTab] = useState(0)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    loadPendingApprovals()
  }, [])

  const loadPendingApprovals = async () => {
    try {
      const [reqRes, invRes] = await Promise.all([
        axios.get('/requisitions?status=pending_approval'),
        axios.get('/invoices/pending-approvals'),
      ])

      setPendingRequisitions(reqRes.data.requisitions || [])
      setPendingInvoices(invRes.data.invoices || [])
    } catch (error) {
      console.error('Failed to load pending approvals', error)
    } finally {
      setLoading(false)
    }
  }

  const handleApprove = async (type, id) => {
    try {
      if (type === 'requisition') {
        await axios.post(`/requisitions/${id}/approve`, { comments })
      } else {
        await axios.post(`/invoices/${id}/approve`, { comments })
      }
      alert('Approved successfully!')
      setDialogOpen(false)
      setComments('')
      loadPendingApprovals()
    } catch (error) {
      alert('Failed to approve')
    }
  }

  const handleReject = async (type, id) => {
    if (!comments.trim()) {
      alert('Please provide comments for rejection')
      return
    }

    try {
      if (type === 'requisition') {
        await axios.post(`/requisitions/${id}/reject`, { comments })
      } else {
        await axios.post(`/invoices/${id}/reject`, { comments })
      }
      alert('Rejected successfully!')
      setDialogOpen(false)
      setComments('')
      loadPendingApprovals()
    } catch (error) {
      alert('Failed to reject')
    }
  }

  const openDetails = async (type, item) => {
    if (type === 'requisition') {
      try {
        const response = await axios.get(`/requisitions/${item.id}`)
        setSelectedItem({ ...response.data, type: 'requisition' })
        setDialogOpen(true)
      } catch (error) {
        alert('Failed to load details')
      }
    } else {
      setSelectedItem({ ...item, type: 'invoice' })
      setDialogOpen(true)
    }
  }

  if (loading) return <Box sx={{ p: 3 }}>Loading...</Box>

  return (
    <Container maxWidth="lg">
      <Box sx={{ mt: 4, mb: 4 }}>
        <Typography variant="h4" gutterBottom>
          Pending Approvals
        </Typography>

        <Tabs value={tab} onChange={(e, val) => setTab(val)} sx={{ mb: 2 }}>
          <Tab label={`Requisitions (${pendingRequisitions.length})`} />
          <Tab label={`Invoices (${pendingInvoices.length})`} />
        </Tabs>

        {tab === 0 && (
          <Paper>
            {pendingRequisitions.length === 0 ? (
              <Box sx={{ p: 3, textAlign: 'center' }}>
                <Typography color="text.secondary">
                  No pending requisition approvals
                </Typography>
              </Box>
            ) : (
              <List>
                {pendingRequisitions.map((req) => (
                  <ListItem
                    key={req.id}
                    button
                    onClick={() => openDetails('requisition', req)}
                  >
                    <ListItemText
                      primary={`Requisition ${req.requisitionNumber}`}
                      secondary={`$${req.totalAmount} ${req.currency} - ${req.itemsCount} items`}
                    />
                    <Chip label={req.status} color="warning" size="small" />
                  </ListItem>
                ))}
              </List>
            )}
          </Paper>
        )}

        {tab === 1 && (
          <Paper>
            {pendingInvoices.length === 0 ? (
              <Box sx={{ p: 3, textAlign: 'center' }}>
                <Typography color="text.secondary">
                  No pending invoice approvals
                </Typography>
              </Box>
            ) : (
              <List>
                {pendingInvoices.map((inv) => (
                  <ListItem
                    key={inv.id}
                    button
                    onClick={() => openDetails('invoice', inv)}
                  >
                    <ListItemText
                      primary={`Invoice ${inv.invoiceNumber}`}
                      secondary={`${inv.supplier.name} - $${inv.amount} ${inv.currency}`}
                    />
                    <Chip label="Pending" color="warning" size="small" />
                  </ListItem>
                ))}
              </List>
            )}
          </Paper>
        )}
      </Box>

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="md" fullWidth>
        {selectedItem && (
          <>
            <DialogTitle>
              {selectedItem.type === 'requisition'
                ? `Requisition ${selectedItem.requisitionNumber}`
                : `Invoice ${selectedItem.invoiceNumber}`}
            </DialogTitle>
            <DialogContent>
              {selectedItem.type === 'requisition' && selectedItem.items && (
                <>
                  <Typography variant="h6" gutterBottom>Items</Typography>
                  <TableContainer>
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>Description</TableCell>
                          <TableCell>Qty</TableCell>
                          <TableCell>Price</TableCell>
                          <TableCell>Total</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {selectedItem.items.map((item) => (
                          <TableRow key={item.id}>
                            <TableCell>{item.description}</TableCell>
                            <TableCell>{item.quantity}</TableCell>
                            <TableCell>${item.unitPrice}</TableCell>
                            <TableCell>${item.subtotal.toFixed(2)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </TableContainer>
                  <Typography variant="h6" sx={{ mt: 2 }}>
                    Total: ${selectedItem.totalAmount} {selectedItem.currency}
                  </Typography>
                </>
              )}

              {selectedItem.type === 'invoice' && (
                <>
                  <Typography>Supplier: {selectedItem.supplier.name}</Typography>
                  <Typography>Amount: ${selectedItem.amount} {selectedItem.currency}</Typography>
                  {selectedItem.description && (
                    <Typography>Description: {selectedItem.description}</Typography>
                  )}
                </>
              )}

              <TextField
                fullWidth
                label="Comments (optional for approval, required for rejection)"
                multiline
                rows={3}
                value={comments}
                onChange={(e) => setComments(e.target.value)}
                sx={{ mt: 3 }}
              />
            </DialogContent>
            <DialogActions>
              <Button onClick={() => setDialogOpen(false)}>Cancel</Button>
              <Button
                color="error"
                onClick={() => handleReject(selectedItem.type, selectedItem.id)}
              >
                Reject
              </Button>
              <Button
                variant="contained"
                color="success"
                onClick={() => handleApprove(selectedItem.type, selectedItem.id)}
              >
                Approve
              </Button>
            </DialogActions>
          </>
        )}
      </Dialog>
    </Container>
  )
}

export default ApprovalsPage
