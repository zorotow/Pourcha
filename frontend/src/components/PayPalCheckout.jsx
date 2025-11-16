import { useState, useEffect } from 'react'
import { Button, Box, CircularProgress, Alert } from '@mui/material'
import { checkoutService } from '../services/api'

function PayPalCheckout({ plan, interval, onSuccess, onError }) {
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [scriptLoaded, setScriptLoaded] = useState(false)

  useEffect(() => {
    // Load PayPal SDK
    const clientId = import.meta.env.VITE_PAYPAL_CLIENT_ID

    if (!clientId) {
      setError('PayPal is not configured')
      return
    }

    const script = document.createElement('script')
    script.src = `https://www.paypal.com/sdk/js?client-id=${clientId}&vault=true&intent=subscription`
    script.async = true
    script.onload = () => setScriptLoaded(true)
    script.onerror = () => setError('Failed to load PayPal SDK')

    document.body.appendChild(script)

    return () => {
      if (document.body.contains(script)) {
        document.body.removeChild(script)
      }
    }
  }, [])

  const handleCheckout = async () => {
    setLoading(true)
    setError('')

    try {
      const response = await checkoutService.createCheckout(plan, 'paypal', interval)

      // Redirect to PayPal approval URL
      if (response.approve_url) {
        window.location.href = response.approve_url
      } else {
        throw new Error('No approval URL received')
      }
    } catch (err) {
      const errorMsg = err.response?.data?.error || 'Failed to create PayPal subscription'
      setError(errorMsg)
      onError(errorMsg)
      setLoading(false)
    }
  }

  return (
    <Box>
      {error && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      <Button
        variant="contained"
        sx={{
          bgcolor: '#0070ba',
          '&:hover': { bgcolor: '#005ea6' },
        }}
        onClick={handleCheckout}
        disabled={loading || !scriptLoaded}
        fullWidth
        size="large"
      >
        {loading ? (
          <>
            <CircularProgress size={24} sx={{ mr: 1, color: 'white' }} />
            Processing...
          </>
        ) : !scriptLoaded ? (
          'Loading PayPal...'
        ) : (
          'Pay with PayPal'
        )}
      </Button>
    </Box>
  )
}

export default PayPalCheckout
