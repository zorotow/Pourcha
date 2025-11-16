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
  FormControl,
  InputLabel,
  Select,
  MenuItem,
} from '@mui/material'
import axios from '../services/api'

function InvoicesPage() {
  const [invoices, setInvoices] = useState([])
  const [suppliers, setSuppliers] = useState([])
  const [createDialogOpen, setCreateDialogOpen] = useState(false)
  const [loading, setLoading] = useState(true)
  const [formData, setFormData] = useState({
    invoiceNumber: '',
    amount: '',
    supplierId: '',
    description: '',
    invoiceDate: new Date().toISOString().split('T')[0],
  })

  useEffect(() => {
    loadInvoices()
    loadSuppliers()
  }, [])

  const loadInvoices = async () => {
    try {
      const response = await axios.get('/invoices')
      setInvoices(response.data.invoices)
    } catch (error) {
      console.error('Failed to load invoices', error)
    } finally {
      setLoading(false)
    }
  }

  const loadSuppliers = async () => {
    try {
      const response = await axios.get('/catalog/suppliers')
      setSuppliers(response.data.suppliers)
    } catch (error) {
      console.error('Failed to load suppliers', error)
    }
  }

  const handleCreateInvoice = async () => {
    try {
      await axios.post('/invoices', formData)
      alert('Invoice created successfully!')
      setCreateDialogOpen(false)
      loadInvoices()
      setFormData({
        invoiceNumber: '',
        amount: '',
        supplierId: '',
        description: '',
        invoiceDate: new Date().toISOString().split('T')[0],
      })
    } catch (error) {
      alert('Failed to create invoice')
    }
  }

  const getStatusColor = (status) => {
    switch (status) {
      case 'approved': return 'success'
      case 'pending_approval': return 'warning'
      case 'rejected': return 'error'
      case 'paid': return 'info'
      default: return 'default'
    }
  }

  if (loading) return <Box sx={{ p: 3 }}>Loading...</Box>

  return (
    <Container maxWidth="lg">
      <Box sx={{ mt: 4, mb: 4 }}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
          <Typography variant="h4">Invoices</Typography>
          <Button variant="contained" onClick={() => setCreateDialogOpen(true)}>
            Submit Invoice
          </Button>
        </Box>

        <TableContainer component={Paper}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>Invoice #</TableCell>
                <TableCell>Supplier</TableCell>
                <TableCell>Amount</TableCell>
                <TableCell>Status</TableCell>
                <TableCell>Invoice Date</TableCell>
                <TableCell>Created</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {invoices.map((inv) => (
                <TableRow key={inv.id}>
                  <TableCell>{inv.invoiceNumber}</TableCell>
                  <TableCell>{inv.supplier.name}</TableCell>
                  <TableCell>${inv.amount} {inv.currency}</TableCell>
                  <TableCell>
                    <Chip label={inv.status} color={getStatusColor(inv.status)} size="small" />
                  </TableCell>
                  <TableCell>{inv.invoiceDate || '-'}</TableCell>
                  <TableCell>{new Date(inv.createdAt).toLocaleDateString()}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>

        {invoices.length === 0 && (
          <Box sx={{ textAlign: 'center', py: 4 }}>
            <Typography color="text.secondary">
              No invoices found
            </Typography>
          </Box>
        )}
      </Box>

      <Dialog open={createDialogOpen} onClose={() => setCreateDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Submit Invoice</DialogTitle>
        <DialogContent>
          <TextField
            fullWidth
            label="Invoice Number"
            value={formData.invoiceNumber}
            onChange={(e) => setFormData({ ...formData, invoiceNumber: e.target.value })}
            sx={{ mt: 2, mb: 2 }}
            required
          />
          <FormControl fullWidth sx={{ mb: 2 }}>
            <InputLabel>Supplier</InputLabel>
            <Select
              value={formData.supplierId}
              onChange={(e) => setFormData({ ...formData, supplierId: e.target.value })}
              required
            >
              {suppliers.map((s) => (
                <MenuItem key={s.id} value={s.id}>{s.name}</MenuItem>
              ))}
            </Select>
          </FormControl>
          <TextField
            fullWidth
            label="Amount"
            type="number"
            value={formData.amount}
            onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
            sx={{ mb: 2 }}
            required
          />
          <TextField
            fullWidth
            label="Invoice Date"
            type="date"
            value={formData.invoiceDate}
            onChange={(e) => setFormData({ ...formData, invoiceDate: e.target.value })}
            sx={{ mb: 2 }}
            InputLabelProps={{ shrink: true }}
          />
          <TextField
            fullWidth
            label="Description"
            multiline
            rows={3}
            value={formData.description}
            onChange={(e) => setFormData({ ...formData, description: e.target.value })}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setCreateDialogOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={handleCreateInvoice}>
            Submit
          </Button>
        </DialogActions>
      </Dialog>
    </Container>
  )
}

export default InvoicesPage
