import React, { useState } from 'react'
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Typography,
  Box,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Alert,
  Chip,
  CircularProgress,
  Grid,
  Paper,
  Divider,
} from '@mui/material'
import {
  CheckCircle,
  Error as ErrorIcon,
  Warning,
} from '@mui/icons-material'
import axios from 'axios'

export default function ThreeWayMatchingDialog({ open, onClose, receipt, invoice }) {
  const [matchResult, setMatchResult] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  React.useEffect(() => {
    if (open && receipt && invoice) {
      performMatch()
    }
  }, [open, receipt, invoice])

  const performMatch = async () => {
    setLoading(true)
    setError(null)

    try {
      const response = await axios.post(`/api/goods-receipts/${receipt.id}/three-way-match`, {
        invoice_id: invoice.id,
      })

      setMatchResult(response.data)
      setLoading(false)
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to perform matching')
      setLoading(false)
    }
  }

  const getStatusIcon = (status) => {
    switch (status) {
      case 'matched':
        return <CheckCircle color="success" />
      case 'partial_match':
        return <Warning color="warning" />
      case 'failed':
        return <ErrorIcon color="error" />
      default:
        return null
    }
  }

  const getStatusColor = (status) => {
    switch (status) {
      case 'matched':
        return 'success'
      case 'partial_match':
        return 'warning'
      case 'failed':
        return 'error'
      default:
        return 'default'
    }
  }

  const getSeverityColor = (severity) => {
    return severity === 'error' ? 'error' : 'warning'
  }

  return (
    <Dialog open={open} onClose={onClose} maxWidth="md" fullWidth>
      <DialogTitle>
        <Box display="flex" alignItems="center">
          {matchResult && getStatusIcon(matchResult.status)}
          <Typography variant="h6" sx={{ ml: 1 }}>
            Three-Way Matching
          </Typography>
        </Box>
      </DialogTitle>

      <DialogContent>
        {loading && (
          <Box display="flex" justifyContent="center" py={4}>
            <CircularProgress />
          </Box>
        )}

        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}

        {matchResult && (
          <>
            {/* Summary */}
            <Paper elevation={0} sx={{ p: 2, mb: 3, bgcolor: 'grey.50' }}>
              <Grid container spacing={2}>
                <Grid item xs={12}>
                  <Box display="flex" alignItems="center" justifyContent="space-between">
                    <Typography variant="subtitle1">
                      Match Status
                    </Typography>
                    <Chip
                      icon={getStatusIcon(matchResult.status)}
                      label={matchResult.status.replace('_', ' ').toUpperCase()}
                      color={getStatusColor(matchResult.status)}
                    />
                  </Box>
                </Grid>

                <Grid item xs={12}>
                  <Divider />
                </Grid>

                <Grid item xs={4}>
                  <Typography variant="body2" color="text.secondary">
                    PO Number
                  </Typography>
                  <Typography variant="body1">
                    {matchResult.summary.po_number}
                  </Typography>
                </Grid>

                <Grid item xs={4}>
                  <Typography variant="body2" color="text.secondary">
                    Invoice Number
                  </Typography>
                  <Typography variant="body1">
                    {matchResult.summary.invoice_number}
                  </Typography>
                </Grid>

                <Grid item xs={4}>
                  <Typography variant="body2" color="text.secondary">
                    Receipt Number
                  </Typography>
                  <Typography variant="body1">
                    {matchResult.summary.receipt_number}
                  </Typography>
                </Grid>

                <Grid item xs={6}>
                  <Typography variant="body2" color="text.secondary">
                    PO Amount
                  </Typography>
                  <Typography variant="body1">
                    ${parseFloat(matchResult.summary.po_amount).toFixed(2)}
                  </Typography>
                </Grid>

                <Grid item xs={6}>
                  <Typography variant="body2" color="text.secondary">
                    Invoice Amount
                  </Typography>
                  <Typography variant="body1">
                    ${parseFloat(matchResult.summary.invoice_amount).toFixed(2)}
                  </Typography>
                </Grid>

                {matchResult.summary.amount_difference > 0 && (
                  <Grid item xs={12}>
                    <Alert severity="info" sx={{ mt: 1 }}>
                      Amount difference: ${parseFloat(matchResult.summary.amount_difference).toFixed(2)}
                    </Alert>
                  </Grid>
                )}
              </Grid>
            </Paper>

            {/* Recommended Action */}
            {matchResult.matched && (
              <Alert severity="success" sx={{ mb: 3 }}>
                <Typography variant="subtitle2" gutterBottom>
                  All matching criteria met
                </Typography>
                <Typography variant="body2">
                  This invoice can be approved for payment.
                </Typography>
              </Alert>
            )}

            {/* Discrepancies */}
            {matchResult.discrepancies && matchResult.discrepancies.length > 0 && (
              <Box mb={3}>
                <Typography variant="h6" gutterBottom>
                  Discrepancies ({matchResult.discrepancies.length})
                </Typography>

                {matchResult.discrepancies.map((discrepancy, index) => (
                  <Alert
                    key={index}
                    severity={getSeverityColor(discrepancy.severity)}
                    sx={{ mb: 1 }}
                  >
                    <Typography variant="subtitle2">
                      {discrepancy.type.replace('_', ' ').toUpperCase()}
                    </Typography>
                    <Typography variant="body2">
                      {discrepancy.message}
                    </Typography>

                    {discrepancy.difference_percent && (
                      <Typography variant="caption" display="block" sx={{ mt: 1 }}>
                        Variance: {discrepancy.difference_percent.toFixed(2)}%
                      </Typography>
                    )}
                  </Alert>
                ))}
              </Box>
            )}

            {/* Warnings */}
            {matchResult.warnings && matchResult.warnings.length > 0 && (
              <Box mb={3}>
                <Typography variant="h6" gutterBottom>
                  Warnings ({matchResult.warnings.length})
                </Typography>

                {matchResult.warnings.map((warning, index) => (
                  <Alert key={index} severity="warning" sx={{ mb: 1 }}>
                    <Typography variant="body2">
                      {warning.message}
                    </Typography>
                  </Alert>
                ))}
              </Box>
            )}

            {/* Item-Level Details (if quantity discrepancies exist) */}
            {matchResult.discrepancies?.some((d) => d.type === 'quantity_mismatch') && (
              <Box>
                <Typography variant="h6" gutterBottom>
                  Item Details
                </Typography>

                <TableContainer>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>Item</TableCell>
                        <TableCell align="right">Ordered</TableCell>
                        <TableCell align="right">Received</TableCell>
                        <TableCell align="right">Accepted</TableCell>
                        <TableCell align="right">Variance</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {matchResult.discrepancies
                        .filter((d) => d.type === 'quantity_mismatch')
                        .map((discrepancy, index) => (
                          <TableRow key={index}>
                            <TableCell>{discrepancy.item_description}</TableCell>
                            <TableCell align="right">
                              {discrepancy.ordered_quantity}
                            </TableCell>
                            <TableCell align="right">
                              {discrepancy.received_quantity}
                            </TableCell>
                            <TableCell align="right">
                              {discrepancy.accepted_quantity}
                            </TableCell>
                            <TableCell align="right">
                              <Chip
                                label={`${discrepancy.difference_percent.toFixed(1)}%`}
                                color={
                                  discrepancy.severity === 'error' ? 'error' : 'warning'
                                }
                                size="small"
                              />
                            </TableCell>
                          </TableRow>
                        ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              </Box>
            )}
          </>
        )}
      </DialogContent>

      <DialogActions>
        <Button onClick={onClose}>Close</Button>
        {matchResult && matchResult.matched && (
          <Button variant="contained" color="success">
            Approve for Payment
          </Button>
        )}
        {matchResult && matchResult.status === 'partial_match' && (
          <Button variant="contained" color="warning">
            Review & Approve
          </Button>
        )}
      </DialogActions>
    </Dialog>
  )
}
