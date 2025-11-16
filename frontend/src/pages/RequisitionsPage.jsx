import { useState, useEffect } from 'react'
import {
  Container,
  Typography,
  Box,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Chip,
  Button,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  Tabs,
  Tab,
} from '@mui/material'
import { useNavigate } from 'react-router-dom'
import axios from '../services/api'

function RequisitionsPage() {
  const navigate = useNavigate()
  const [requisitions, setRequisitions] = useState([])
  const [loading, setLoading] = useState(true)
  const [selectedReq, setSelectedReq] = useState(null)
  const [detailsOpen, setDetailsOpen] = useState(false)

  useEffect(() => {
    loadRequisitions()
  }, [])

  const loadRequisitions = async () => {
    try {
      const response = await axios.get('/requisitions')
      setRequisitions(response.data.requisitions)
    } catch (error) {
      console.error('Failed to load requisitions', error)
    } finally {
      setLoading(false)
    }
  }

  const loadRequisitionDetails = async (id) => {
    try {
      const response = await axios.get(`/requisitions/${id}`)
      setSelectedReq(response.data)
      setDetailsOpen(true)
    } catch (error) {
      alert('Failed to load requisition details')
    }
  }

  const getStatusColor = (status) => {
    switch (status) {
      case 'approved': return 'success'
      case 'pending_approval': return 'warning'
      case 'rejected': return 'error'
      default: return 'default'
    }
  }

  if (loading) return <Box sx={{ p: 3 }}>Loading...</Box>

  return (
    <Container maxWidth="lg">
      <Box sx={{ mt: 4, mb: 4 }}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
          <Typography variant="h4">My Requisitions</Typography>
          <Button variant="contained" onClick={() => navigate('/cart')}>
            View Cart
          </Button>
        </Box>

        <TableContainer component={Paper}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>Requisition #</TableCell>
                <TableCell>Status</TableCell>
                <TableCell>Total Amount</TableCell>
                <TableCell>Items</TableCell>
                <TableCell>Submitted</TableCell>
                <TableCell>Actions</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {requisitions.map((req) => (
                <TableRow key={req.id}>
                  <TableCell>{req.requisitionNumber}</TableCell>
                  <TableCell>
                    <Chip label={req.status} color={getStatusColor(req.status)} size="small" />
                  </TableCell>
                  <TableCell>${req.totalAmount} {req.currency}</TableCell>
                  <TableCell>{req.itemsCount}</TableCell>
                  <TableCell>{req.submittedAt ? new Date(req.submittedAt).toLocaleDateString() : '-'}</TableCell>
                  <TableCell>
                    <Button size="small" onClick={() => loadRequisitionDetails(req.id)}>
                      View
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>

        {requisitions.length === 0 && (
          <Box sx={{ textAlign: 'center', py: 4 }}>
            <Typography color="text.secondary">
              No requisitions found
            </Typography>
          </Box>
        )}
      </Box>

      <Dialog open={detailsOpen} onClose={() => setDetailsOpen(false)} maxWidth="md" fullWidth>
        {selectedReq && (
          <>
            <DialogTitle>
              Requisition {selectedReq.requisitionNumber}
              <Chip label={selectedReq.status} color={getStatusColor(selectedReq.status)} sx={{ ml: 2 }} />
            </DialogTitle>
            <DialogContent>
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
                    {selectedReq.items.map((item) => (
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

              {selectedReq.approvals.length > 0 && (
                <>
                  <Typography variant="h6" sx={{ mt: 3 }} gutterBottom>Approvals</Typography>
                  {selectedReq.approvals.map((approval) => (
                    <Box key={approval.id} sx={{ mb: 1 }}>
                      <Typography variant="body2">
                        {approval.approver.name} - <Chip label={approval.status} size="small" />
                      </Typography>
                      {approval.comments && (
                        <Typography variant="caption" color="text.secondary">
                          {approval.comments}
                        </Typography>
                      )}
                    </Box>
                  ))}
                </>
              )}

              <Typography variant="h6" sx={{ mt: 2 }}>
                Total: ${selectedReq.totalAmount} {selectedReq.currency}
              </Typography>
            </DialogContent>
            <DialogActions>
              <Button onClick={() => setDetailsOpen(false)}>Close</Button>
            </DialogActions>
          </>
        )}
      </Dialog>
    </Container>
  )
}

export default RequisitionsPage
