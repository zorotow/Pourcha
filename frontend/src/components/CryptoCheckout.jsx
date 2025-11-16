import { useState, useEffect } from 'react'
import {
  Button,
  Box,
  CircularProgress,
  Alert,
  Typography,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Paper,
  TextField,
  IconButton,
} from '@mui/material'
import { ContentCopy as CopyIcon } from '@mui/icons-material'
import QRCode from 'qrcode.react'
import { checkoutService, orderService } from '../services/api'

function CryptoCheckout({ plan, interval, onSuccess, onError }) {
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [currency, setCurrency] = useState('USDT_SOLANA')
  const [paymentData, setPaymentData] = useState(null)
  const [checking, setChecking] = useState(false)

  const handleCurrencyChange = (event) => {
    setCurrency(event.target.value)
  }

  const handleCreatePayment = async () => {
    setLoading(true)
    setError('')

    try {
      const response = await checkoutService.createCheckout(
        plan,
        'crypto',
        interval,
        currency
      )

      setPaymentData(response)
    } catch (err) {
      const errorMsg =
        err.response?.data?.error || 'Failed to create crypto payment'
      setError(errorMsg)
      onError(errorMsg)
    } finally {
      setLoading(false)
    }
  }

  const handleCopyAddress = () => {
    if (paymentData?.wallet_address) {
      navigator.clipboard.writeText(paymentData.wallet_address)
      alert('Wallet address copied to clipboard!')
    }
  }

  const handleCheckPayment = async () => {
    if (!paymentData?.payment_id) return

    setChecking(true)
    try {
      const details = await checkoutService.getCryptoPaymentDetails(
        paymentData.payment_id
      )

      if (details.status === 'completed') {
        onSuccess()
      } else if (details.status === 'failed') {
        setError('Payment verification failed')
      } else {
        alert('Payment is still pending. Please wait for confirmation.')
      }
    } catch (err) {
      setError('Failed to check payment status')
    } finally {
      setChecking(false)
    }
  }

  const handleSimulatePayment = async () => {
    if (!paymentData?.payment_id) return

    setChecking(true)
    try {
      // Create an order for simulation
      const order = await orderService.createOrder(
        plan,
        'crypto',
        paymentData.amount * 100
      )

      // Simulate successful payment
      await orderService.simulatePayment(order.order_id, true)

      onSuccess()
    } catch (err) {
      setError('Failed to simulate payment')
    } finally {
      setChecking(false)
    }
  }

  return (
    <Box>
      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      {!paymentData ? (
        <Box>
          <FormControl fullWidth sx={{ mb: 3 }}>
            <InputLabel>Cryptocurrency</InputLabel>
            <Select
              value={currency}
              onChange={handleCurrencyChange}
              label="Cryptocurrency"
            >
              <MenuItem value="USDT_SOLANA">USDT (Solana)</MenuItem>
              <MenuItem value="USDC_SOLANA">USDC (Solana)</MenuItem>
              <MenuItem value="BTC">Bitcoin (BTC)</MenuItem>
              <MenuItem value="MONERO">Monero (XMR)</MenuItem>
            </Select>
          </FormControl>

          <Button
            variant="contained"
            onClick={handleCreatePayment}
            disabled={loading}
            fullWidth
            size="large"
            sx={{
              bgcolor: '#f7931a',
              '&:hover': { bgcolor: '#e08312' },
            }}
          >
            {loading ? (
              <>
                <CircularProgress size={24} sx={{ mr: 1, color: 'white' }} />
                Generating...
              </>
            ) : (
              'Generate Crypto Payment'
            )}
          </Button>
        </Box>
      ) : (
        <Paper sx={{ p: 3 }}>
          <Typography variant="h6" gutterBottom align="center">
            Send Exactly
          </Typography>

          <Typography
            variant="h4"
            color="primary"
            gutterBottom
            align="center"
            sx={{ fontWeight: 'bold' }}
          >
            {paymentData.amount} {paymentData.currency}
          </Typography>

          <Box sx={{ display: 'flex', justifyContent: 'center', my: 3 }}>
            <QRCode value={paymentData.wallet_address} size={200} />
          </Box>

          <Typography variant="subtitle2" gutterBottom>
            Wallet Address:
          </Typography>
          <Box sx={{ display: 'flex', alignItems: 'center', mb: 3 }}>
            <TextField
              fullWidth
              value={paymentData.wallet_address}
              InputProps={{
                readOnly: true,
              }}
              size="small"
            />
            <IconButton onClick={handleCopyAddress} color="primary">
              <CopyIcon />
            </IconButton>
          </Box>

          <Alert severity="info" sx={{ mb: 2 }}>
            Please send the exact amount to the address above. Payment confirmation
            may take 5-30 minutes depending on network congestion.
          </Alert>

          <Box sx={{ display: 'flex', gap: 2 }}>
            <Button
              variant="contained"
              onClick={handleCheckPayment}
              disabled={checking}
              fullWidth
            >
              {checking ? (
                <>
                  <CircularProgress size={20} sx={{ mr: 1 }} />
                  Checking...
                </>
              ) : (
                'I Have Sent Payment'
              )}
            </Button>

            <Button
              variant="outlined"
              onClick={handleSimulatePayment}
              disabled={checking}
              fullWidth
              color="warning"
            >
              Simulate Payment (Test)
            </Button>
          </Box>

          <Typography variant="caption" color="text.secondary" sx={{ mt: 2, display: 'block' }}>
            Payment ID: {paymentData.payment_id}
          </Typography>
        </Paper>
      )}
    </Box>
  )
}

export default CryptoCheckout
