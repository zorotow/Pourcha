import { useState, useEffect } from 'react'
import {
  Box,
  Container,
  Typography,
  Paper,
  Grid,
  Card,
  CardContent,
  CardActions,
  Button,
  Chip,
  Alert,
  CircularProgress,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Tabs,
  Tab,
  List,
  ListItem,
  ListItemText,
  Divider,
} from '@mui/material'
import { loadStripe } from '@stripe/stripe-js'
import { Elements } from '@stripe/react-stripe-js'
import { subscriptionService, checkoutService } from '../services/api'
import StripeCheckout from '../components/StripeCheckout'
import PayPalCheckout from '../components/PayPalCheckout'
import CryptoCheckout from '../components/CryptoCheckout'

const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY)

function BillingPage() {
  const [subscription, setSubscription] = useState(null)
  const [plans, setPlans] = useState([])
  const [payments, setPayments] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [upgradeDialogOpen, setUpgradeDialogOpen] = useState(false)
  const [selectedPlan, setSelectedPlan] = useState(null)
  const [paymentMethod, setPaymentMethod] = useState(0) // 0: Stripe, 1: PayPal, 2: Crypto
  const [billingInterval, setBillingInterval] = useState('monthly')

  useEffect(() => {
    loadData()
  }, [])

  const loadData = async () => {
    setLoading(true)
    try {
      const [subData, plansData, historyData] = await Promise.all([
        subscriptionService.getSubscription(),
        checkoutService.getPlans(),
        subscriptionService.getPaymentHistory(),
      ])

      setSubscription(subData)
      setPlans(plansData)
      setPayments(historyData.payments || [])
    } catch (err) {
      setError('Failed to load billing information')
      console.error(err)
    } finally {
      setLoading(false)
    }
  }

  const handleUpgradeClick = (plan) => {
    setSelectedPlan(plan)
    setUpgradeDialogOpen(true)
  }

  const handleCancelSubscription = async () => {
    if (!window.confirm('Are you sure you want to cancel your subscription?')) {
      return
    }

    try {
      await subscriptionService.cancelSubscription()
      setSuccess('Subscription cancelled successfully')
      loadData()
    } catch (err) {
      setError('Failed to cancel subscription')
      console.error(err)
    }
  }

  const handleCheckoutSuccess = () => {
    setSuccess('Payment processed successfully! Your subscription will be updated shortly.')
    setUpgradeDialogOpen(false)
    setTimeout(() => {
      loadData()
    }, 2000)
  }

  const handleCheckoutError = (message) => {
    setError(message || 'Payment failed. Please try again.')
  }

  const getPlanBadgeColor = (plan) => {
    switch (plan) {
      case 'free':
        return 'default'
      case 'pro':
        return 'primary'
      case 'enterprise':
        return 'secondary'
      default:
        return 'default'
    }
  }

  const getStatusColor = (status) => {
    switch (status) {
      case 'active':
        return 'success'
      case 'cancelled':
        return 'error'
      case 'expired':
        return 'warning'
      default:
        return 'default'
    }
  }

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', mt: 8 }}>
        <CircularProgress />
      </Box>
    )
  }

  return (
    <Container maxWidth="lg">
      <Box sx={{ mt: 4, mb: 4 }}>
        <Typography variant="h4" component="h1" gutterBottom>
          Billing & Subscription
        </Typography>

        {error && (
          <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError('')}>
            {error}
          </Alert>
        )}

        {success && (
          <Alert severity="success" sx={{ mb: 2 }} onClose={() => setSuccess('')}>
            {success}
          </Alert>
        )}

        {/* Current Subscription */}
        <Paper sx={{ p: 3, mb: 3 }}>
          <Typography variant="h6" gutterBottom>
            Current Plan
          </Typography>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, mb: 2 }}>
            <Chip
              label={subscription?.plan?.toUpperCase() || 'FREE'}
              color={getPlanBadgeColor(subscription?.plan)}
              size="medium"
            />
            <Chip
              label={subscription?.status?.toUpperCase() || 'ACTIVE'}
              color={getStatusColor(subscription?.status)}
              size="small"
            />
          </Box>

          {subscription?.renewal_date && (
            <Typography variant="body2" color="text.secondary" gutterBottom>
              Renews on: {new Date(subscription.renewal_date).toLocaleDateString()}
            </Typography>
          )}

          {subscription?.gateway && (
            <Typography variant="body2" color="text.secondary" gutterBottom>
              Payment Method: {subscription.gateway.toUpperCase()}
            </Typography>
          )}

          {subscription?.can_cancel && (
            <Button
              variant="outlined"
              color="error"
              onClick={handleCancelSubscription}
              sx={{ mt: 2 }}
            >
              Cancel Subscription
            </Button>
          )}
        </Paper>

        {/* Available Plans */}
        <Typography variant="h6" gutterBottom sx={{ mt: 4 }}>
          Available Plans
        </Typography>
        <Grid container spacing={3} sx={{ mb: 4 }}>
          {plans.map((plan) => (
            <Grid item xs={12} md={4} key={plan.plan}>
              <Card
                sx={{
                  height: '100%',
                  display: 'flex',
                  flexDirection: 'column',
                  border: plan.plan === subscription?.plan ? 2 : 0,
                  borderColor: 'primary.main',
                }}
              >
                <CardContent sx={{ flexGrow: 1 }}>
                  <Typography variant="h5" component="h2" gutterBottom>
                    {plan.name}
                  </Typography>

                  {plan.price_monthly > 0 ? (
                    <>
                      <Typography variant="h4" color="primary">
                        ${(plan.price_monthly / 100).toFixed(2)}
                        <Typography variant="body2" component="span">
                          /month
                        </Typography>
                      </Typography>
                      <Typography variant="body2" color="text.secondary">
                        or ${(plan.price_yearly / 100).toFixed(2)}/year
                      </Typography>
                    </>
                  ) : plan.description ? (
                    <Typography variant="h6" color="text.secondary">
                      {plan.description}
                    </Typography>
                  ) : (
                    <Typography variant="h4" color="primary">
                      Free
                    </Typography>
                  )}

                  <List dense sx={{ mt: 2 }}>
                    {Object.entries(plan.features).map(([key, value]) => (
                      <ListItem key={key} disablePadding>
                        <ListItemText
                          primary={`${key.replace(/_/g, ' ')}: ${
                            typeof value === 'boolean'
                              ? value
                                ? 'Yes'
                                : 'No'
                              : value === -1
                              ? 'Unlimited'
                              : value
                          }`}
                        />
                      </ListItem>
                    ))}
                  </List>
                </CardContent>
                <CardActions>
                  {plan.plan !== subscription?.plan &&
                    plan.plan !== 'free' && (
                      <Button
                        fullWidth
                        variant="contained"
                        onClick={() => handleUpgradeClick(plan)}
                      >
                        {plan.plan === 'enterprise' ? 'Contact Sales' : 'Upgrade'}
                      </Button>
                    )}
                  {plan.plan === subscription?.plan && (
                    <Chip label="Current Plan" color="primary" sx={{ mx: 'auto' }} />
                  )}
                </CardActions>
              </Card>
            </Grid>
          ))}
        </Grid>

        {/* Payment History */}
        <Paper sx={{ p: 3 }}>
          <Typography variant="h6" gutterBottom>
            Payment History
          </Typography>
          {payments.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              No payment history available
            </Typography>
          ) : (
            <List>
              {payments.map((payment, index) => (
                <Box key={payment.id}>
                  {index > 0 && <Divider />}
                  <ListItem>
                    <ListItemText
                      primary={`${payment.gateway?.toUpperCase()} - $${(
                        payment.amount / 100
                      ).toFixed(2)} ${payment.currency}`}
                      secondary={`${payment.status?.toUpperCase()} - ${new Date(
                        payment.created_at
                      ).toLocaleDateString()}`}
                    />
                    {payment.transaction_id && (
                      <Typography variant="caption" color="text.secondary">
                        TX: {payment.transaction_id}
                      </Typography>
                    )}
                  </ListItem>
                </Box>
              ))}
            </List>
          )}
        </Paper>
      </Box>

      {/* Upgrade Dialog */}
      <Dialog
        open={upgradeDialogOpen}
        onClose={() => setUpgradeDialogOpen(false)}
        maxWidth="md"
        fullWidth
      >
        <DialogTitle>
          Upgrade to {selectedPlan?.name}
          <Typography variant="body2" color="text.secondary">
            Choose your payment method and billing interval
          </Typography>
        </DialogTitle>
        <DialogContent>
          {/* Billing Interval Selection */}
          <Box sx={{ mb: 3 }}>
            <Typography variant="subtitle2" gutterBottom>
              Billing Interval
            </Typography>
            <Tabs
              value={billingInterval}
              onChange={(e, val) => setBillingInterval(val)}
            >
              <Tab label="Monthly" value="monthly" />
              <Tab label="Yearly (Save 17%)" value="yearly" />
            </Tabs>
          </Box>

          {/* Payment Method Selection */}
          <Tabs value={paymentMethod} onChange={(e, val) => setPaymentMethod(val)}>
            <Tab label="Credit Card (Stripe)" />
            <Tab label="PayPal" />
            <Tab label="Crypto" />
          </Tabs>

          <Box sx={{ mt: 3 }}>
            {paymentMethod === 0 && (
              <Elements stripe={stripePromise}>
                <StripeCheckout
                  plan={selectedPlan?.plan}
                  interval={billingInterval}
                  onSuccess={handleCheckoutSuccess}
                  onError={handleCheckoutError}
                />
              </Elements>
            )}

            {paymentMethod === 1 && (
              <PayPalCheckout
                plan={selectedPlan?.plan}
                interval={billingInterval}
                onSuccess={handleCheckoutSuccess}
                onError={handleCheckoutError}
              />
            )}

            {paymentMethod === 2 && (
              <CryptoCheckout
                plan={selectedPlan?.plan}
                interval={billingInterval}
                onSuccess={handleCheckoutSuccess}
                onError={handleCheckoutError}
              />
            )}
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setUpgradeDialogOpen(false)}>Cancel</Button>
        </DialogActions>
      </Dialog>
    </Container>
  )
}

export default BillingPage
