import React, { useState, useEffect } from 'react'
import {
  Paper,
  Typography,
  List,
  ListItem,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Chip,
  Box,
  IconButton,
  Collapse,
} from '@mui/material'
import {
  CheckCircle,
  RadioButtonUnchecked,
  Assignment,
  Receipt,
  Description,
  ExpandMore,
  ExpandLess,
} from '@mui/icons-material'
import { useNavigate } from 'react-router-dom'
import axios from 'axios'

export default function ToDoList({ compact = false }) {
  const navigate = useNavigate()
  const [todos, setTodos] = useState([])
  const [loading, setLoading] = useState(true)
  const [expanded, setExpanded] = useState(true)

  useEffect(() => {
    loadTodos()
  }, [])

  const loadTodos = async () => {
    try {
      const response = await axios.get('/api/todos')
      setTodos(response.data.todos || [])
      setLoading(false)
    } catch (err) {
      console.error('Failed to load todos:', err)
      setLoading(false)
    }
  }

  const handleTodoClick = (todo) => {
    switch (todo.type) {
      case 'approval_requisition':
        navigate(`/approvals?tab=0&highlight=${todo.itemId}`)
        break
      case 'approval_invoice':
        navigate(`/approvals?tab=1&highlight=${todo.itemId}`)
        break
      case 'goods_receipt':
        navigate(`/goods-receipts/${todo.itemId}`)
        break
      default:
        navigate(todo.link)
    }
  }

  const getIcon = (type) => {
    switch (type) {
      case 'approval_requisition':
      case 'approval_invoice':
        return <Assignment color="primary" />
      case 'goods_receipt':
        return <Receipt color="warning" />
      default:
        return <Description />
    }
  }

  const getPriorityColor = (priority) => {
    switch (priority) {
      case 'high':
        return 'error'
      case 'medium':
        return 'warning'
      case 'low':
        return 'default'
      default:
        return 'default'
    }
  }

  if (loading || todos.length === 0) {
    return null
  }

  const pendingTodos = todos.filter((t) => !t.completed)

  if (compact) {
    return (
      <Paper elevation={2} sx={{ p: 2 }}>
        <Box display="flex" alignItems="center" justifyContent="space-between" mb={1}>
          <Typography variant="h6">
            To-Do List ({pendingTodos.length})
          </Typography>
          <IconButton size="small" onClick={() => setExpanded(!expanded)}>
            {expanded ? <ExpandLess /> : <ExpandMore />}
          </IconButton>
        </Box>

        <Collapse in={expanded}>
          <List dense>
            {pendingTodos.slice(0, 5).map((todo) => (
              <ListItemButton
                key={todo.id}
                onClick={() => handleTodoClick(todo)}
              >
                <ListItemIcon>
                  {todo.completed ? (
                    <CheckCircle color="success" />
                  ) : (
                    <RadioButtonUnchecked />
                  )}
                </ListItemIcon>
                <ListItemText
                  primary={todo.title}
                  secondary={todo.description}
                />
                {todo.priority && (
                  <Chip
                    label={todo.priority}
                    color={getPriorityColor(todo.priority)}
                    size="small"
                  />
                )}
              </ListItemButton>
            ))}
          </List>

          {pendingTodos.length > 5 && (
            <Box mt={1}>
              <Typography
                variant="body2"
                color="primary"
                sx={{ cursor: 'pointer' }}
                onClick={() => navigate('/todos')}
              >
                View all {pendingTodos.length} tasks →
              </Typography>
            </Box>
          )}
        </Collapse>
      </Paper>
    )
  }

  return (
    <Paper sx={{ p: 3 }}>
      <Typography variant="h5" gutterBottom>
        Your To-Do List
      </Typography>

      {pendingTodos.length === 0 ? (
        <Box sx={{ textAlign: 'center', py: 4, color: 'text.secondary' }}>
          <CheckCircle sx={{ fontSize: 60 }} />
          <Typography variant="h6" sx={{ mt: 2 }}>
            All caught up!
          </Typography>
          <Typography variant="body2">
            You have no pending tasks at the moment.
          </Typography>
        </Box>
      ) : (
        <List>
          {pendingTodos.map((todo) => (
            <ListItem
              key={todo.id}
              disablePadding
              secondaryAction={
                todo.priority && (
                  <Chip
                    label={todo.priority}
                    color={getPriorityColor(todo.priority)}
                    size="small"
                  />
                )
              }
            >
              <ListItemButton onClick={() => handleTodoClick(todo)}>
                <ListItemIcon>
                  {getIcon(todo.type)}
                </ListItemIcon>
                <ListItemText
                  primary={todo.title}
                  secondary={
                    <>
                      <Typography
                        component="span"
                        variant="body2"
                        color="text.primary"
                      >
                        {todo.description}
                      </Typography>
                      {todo.dueDate && (
                        <Typography
                          component="span"
                          variant="caption"
                          display="block"
                          sx={{ mt: 0.5 }}
                        >
                          Due: {new Date(todo.dueDate).toLocaleDateString()}
                        </Typography>
                      )}
                    </>
                  }
                />
              </ListItemButton>
            </ListItem>
          ))}
        </List>
      )}
    </Paper>
  )
}
