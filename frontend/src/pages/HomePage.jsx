import { Box, Typography, Button, Container } from '@mui/material'
import { useNavigate } from 'react-router-dom'

function HomePage() {
  const navigate = useNavigate()

  return (
    <Container>
      <Box sx={{ mt: 8, textAlign: 'center' }}>
        <Typography variant="h2" component="h1" gutterBottom>
          Welcome to Pourcha
        </Typography>
        <Typography variant="h5" color="text.secondary" paragraph>
          Procurement and Purchasing Platform
        </Typography>
        <Box sx={{ mt: 4 }}>
          <Button
            variant="contained"
            size="large"
            onClick={() => navigate('/settings/billing')}
            sx={{ mr: 2 }}
          >
            View Billing
          </Button>
          <Button
            variant="outlined"
            size="large"
            onClick={() => navigate('/login')}
          >
            Login
          </Button>
        </Box>
      </Box>
    </Container>
  )
}

export default HomePage
