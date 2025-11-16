import { useState } from 'react'
import { Button, Box, CircularProgress, Alert } from '@mui/material'
import { checkoutService } from '../services/api'

function StripeCheckout({ plan, interval, onSuccess, onError }) {
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  const handleCheckout = async () => {
    setLoading(true)
    setError('')

    try {
      const response = await checkoutService.createCheckout(plan, 'stripe', interval)

      // Redirect to Stripe Checkout
      if (response.session_url) {
        window.location.href = response.session_url
      } else {
        throw new Error('No checkout URL received')
      }
    } catch (err) {
      const errorMsg = err.response?.data?.error || 'Failed to create checkout session'
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
        color="primary"
        onClick={handleCheckout}
        disabled={loading}
        fullWidth
        size="large"
      >
        {loading ? (
          <>
            <CircularProgress size={24} sx={{ mr: 1 }} />
            Processing...
          </>
        ) : (
          'Pay with Stripe'
        )}
      </Button>

      <Box sx={{ mt: 2, textAlign: 'center' }}>
        <img
          src="https://stripe.com/img/v3/home/twitter.png"
          alt="Powered by Stripe"
          style={{ maxWidth: 120, opacity: 0.6 }}
        />
      </Box>
    </Box>
  )
}

export default StripeCheckout
