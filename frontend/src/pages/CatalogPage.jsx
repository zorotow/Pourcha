import { useState, useEffect } from 'react'
import {
  Container,
  Typography,
  Box,
  Grid,
  Card,
  CardContent,
  CardActions,
  Button,
  TextField,
  MenuItem,
  FormControl,
  InputLabel,
  Select,
  Chip,
  IconButton,
  Drawer,
  List,
  ListItem,
  ListItemText,
  Divider,
} from '@mui/material'
import { Add, FilterList } from '@mui/icons-material'
import { useNavigate, useSearchParams } from 'react-router-dom'
import axios from '../services/api'

function CatalogPage() {
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const [items, setItems] = useState([])
  const [categories, setCategories] = useState([])
  const [brands, setBrands] = useState([])
  const [suppliers, setSuppliers] = useState([])
  const [loading, setLoading] = useState(true)
  const [filterDrawerOpen, setFilterDrawerOpen] = useState(false)

  const [filters, setFilters] = useState({
    search: searchParams.get('search') || '',
    category: searchParams.get('category') || '',
    brand: searchParams.get('brand') || '',
    supplier: searchParams.get('supplier') || '',
    minPrice: searchParams.get('minPrice') || '',
    maxPrice: searchParams.get('maxPrice') || '',
    sortBy: searchParams.get('sortBy') || 'name',
  })

  useEffect(() => {
    loadCatalog()
    loadFilters()
  }, [filters])

  const loadCatalog = async () => {
    setLoading(true)
    try {
      const params = new URLSearchParams()
      Object.keys(filters).forEach((key) => {
        if (filters[key]) params.append(key, filters[key])
      })

      const response = await axios.get(`/catalog/items?${params}`)
      setItems(response.data.items)
    } catch (error) {
      console.error('Failed to load catalog', error)
    } finally {
      setLoading(false)
    }
  }

  const loadFilters = async () => {
    try {
      const [categoriesRes, brandsRes, suppliersRes] = await Promise.all([
        axios.get('/catalog/categories'),
        axios.get('/catalog/brands'),
        axios.get('/catalog/suppliers'),
      ])

      setCategories(categoriesRes.data.categories)
      setBrands(brandsRes.data.brands)
      setSuppliers(suppliersRes.data.suppliers)
    } catch (error) {
      console.error('Failed to load filters', error)
    }
  }

  const handleFilterChange = (key, value) => {
    setFilters({ ...filters, [key]: value })
    const newParams = new URLSearchParams(searchParams)
    if (value) {
      newParams.set(key, value)
    } else {
      newParams.delete(key)
    }
    setSearchParams(newParams)
  }

  const handleAddToCart = async (itemId, quantity = 1) => {
    try {
      await axios.post('/cart/items', { catalogItemId: itemId, quantity })
      alert('Item added to cart!')
    } catch (error) {
      alert('Failed to add item to cart')
    }
  }

  return (
    <Container maxWidth="lg">
      <Box sx={{ mt: 4, mb: 4 }}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
          <Typography variant="h4">Catalog - {items.length} items</Typography>
          <Button
            variant="outlined"
            startIcon={<FilterList />}
            onClick={() => setFilterDrawerOpen(true)}
          >
            Filters
          </Button>
        </Box>

        {/* Search */}
        <TextField
          fullWidth
          placeholder="Search items..."
          value={filters.search}
          onChange={(e) => handleFilterChange('search', e.target.value)}
          sx={{ mb: 2 }}
        />

        {/* Active Filters */}
        {(filters.category || filters.brand || filters.supplier) && (
          <Box sx={{ mb: 2 }}>
            {filters.category && (
              <Chip label={`Category: ${filters.category}`} onDelete={() => handleFilterChange('category', '')} sx={{ mr: 1 }} />
            )}
            {filters.brand && (
              <Chip label={`Brand: ${filters.brand}`} onDelete={() => handleFilterChange('brand', '')} sx={{ mr: 1 }} />
            )}
            {filters.supplier && (
              <Chip label={`Supplier: ${suppliers.find(s => s.id === parseInt(filters.supplier))?.name}`} onDelete={() => handleFilterChange('supplier', '')} />
            )}
          </Box>
        )}

        {/* Items Grid */}
        {loading ? (
          <Typography>Loading...</Typography>
        ) : (
          <Grid container spacing={2}>
            {items.map((item) => (
              <Grid item xs={12} sm={6} md={4} lg={3} key={item.id}>
                <Card sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
                  {item.imageUrl && (
                    <Box
                      component="img"
                      src={item.imageUrl}
                      alt={item.name}
                      sx={{ height: 140, objectFit: 'cover' }}
                    />
                  )}
                  <CardContent sx={{ flexGrow: 1 }}>
                    <Typography variant="subtitle1" gutterBottom>
                      {item.name}
                    </Typography>
                    <Typography variant="body2" color="text.secondary" gutterBottom>
                      {item.supplier.name}
                    </Typography>
                    {item.supplierPartNumber && (
                      <Typography variant="caption" color="text.secondary">
                        Part #{item.supplierPartNumber}
                      </Typography>
                    )}
                    <Typography variant="h6" color="primary" sx={{ mt: 1 }}>
                      ${item.price} {item.currency} / {item.unit}
                    </Typography>
                    {item.category && (
                      <Chip label={item.category} size="small" sx={{ mt: 1 }} />
                    )}
                  </CardContent>
                  <CardActions>
                    <TextField
                      type="number"
                      size="small"
                      defaultValue={1}
                      inputProps={{ min: 1 }}
                      sx={{ width: 80 }}
                      id={`qty-${item.id}`}
                    />
                    <Button
                      size="small"
                      variant="contained"
                      startIcon={<Add />}
                      onClick={() => {
                        const qty = parseInt(document.getElementById(`qty-${item.id}`).value)
                        handleAddToCart(item.id, qty)
                      }}
                    >
                      Add
                    </Button>
                  </CardActions>
                </Card>
              </Grid>
            ))}
          </Grid>
        )}
      </Box>

      {/* Filter Drawer */}
      <Drawer anchor="right" open={filterDrawerOpen} onClose={() => setFilterDrawerOpen(false)}>
        <Box sx={{ width: 300, p: 2 }}>
          <Typography variant="h6" gutterBottom>
            Filters
          </Typography>
          <Divider sx={{ mb: 2 }} />

          <FormControl fullWidth sx={{ mb: 2 }}>
            <InputLabel>Supplier</InputLabel>
            <Select
              value={filters.supplier}
              onChange={(e) => handleFilterChange('supplier', e.target.value)}
            >
              <MenuItem value="">All</MenuItem>
              {suppliers.map((s) => (
                <MenuItem key={s.id} value={s.id}>{s.name}</MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl fullWidth sx={{ mb: 2 }}>
            <InputLabel>Category</InputLabel>
            <Select
              value={filters.category}
              onChange={(e) => handleFilterChange('category', e.target.value)}
            >
              <MenuItem value="">All</MenuItem>
              {categories.map((cat) => (
                <MenuItem key={cat} value={cat}>{cat}</MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl fullWidth sx={{ mb: 2 }}>
            <InputLabel>Brand</InputLabel>
            <Select
              value={filters.brand}
              onChange={(e) => handleFilterChange('brand', e.target.value)}
            >
              <MenuItem value="">All</MenuItem>
              {brands.map((brand) => (
                <MenuItem key={brand} value={brand}>{brand}</MenuItem>
              ))}
            </Select>
          </FormControl>

          <Typography variant="subtitle2" gutterBottom>Price Range (AUD)</Typography>
          <TextField
            fullWidth
            label="Min Price"
            type="number"
            value={filters.minPrice}
            onChange={(e) => handleFilterChange('minPrice', e.target.value)}
            sx={{ mb: 1 }}
          />
          <TextField
            fullWidth
            label="Max Price"
            type="number"
            value={filters.maxPrice}
            onChange={(e) => handleFilterChange('maxPrice', e.target.value)}
            sx={{ mb: 2 }}
          />

          <FormControl fullWidth>
            <InputLabel>Sort By</InputLabel>
            <Select
              value={filters.sortBy}
              onChange={(e) => handleFilterChange('sortBy', e.target.value)}
            >
              <MenuItem value="name">Name</MenuItem>
              <MenuItem value="price">Price</MenuItem>
              <MenuItem value="brand">Brand</MenuItem>
            </Select>
          </FormControl>
        </Box>
      </Drawer>
    </Container>
  )
}

export default CatalogPage
