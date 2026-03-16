# Frontend - MockProject

This is the frontend of the project, built with Vite and Tailwind CSS. It communicates with the Laravel backend API.

## Getting Started

### Prerequisites
- Node.js 16+ 
- npm or yarn

### Installation

1. Navigate to the frontend directory:
   ```bash
   cd frontend
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

3. Copy environment file:
   ```bash
   cp .env.example .env
   ```

   Update the `VITE_API_BASE_URL` to match your backend URL:
   ```
   VITE_API_BASE_URL=http://localhost:8000/api
   ```

### Development Server

Start the development server:
```bash
npm run dev
```

The frontend will be available at `http://localhost:5173`

### Build for Production

Build the project:
```bash
npm run build
```

Preview the production build:
```bash
npm run preview
```

## Project Structure

```
src/
├── assets/              # Static assets (CSS, images, fonts)
│   └── app.css         # Global styles
├── components/         # Reusable components
├── views/             # Page components
├── services/          # API service clients
├── main.js            # Vite entry point
└── app.js             # Main application component
```

## Features

- **Vite**: Ultra-fast build tool and dev server
- **Tailwind CSS**: Utility-first CSS framework
- **Axios**: HTTP client for API requests
- **ES Modules**: Modern JavaScript module system

## API Integration

Communication with the backend is handled through Axios. Create services in `src/services/` for API calls:

```javascript
// Example: src/services/userService.js
import axios from 'axios';

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  }
});

export const getUsers = () => apiClient.get('/users');
export const createUser = (userData) => apiClient.post('/users', userData);
```

## Environment Variables

Create a `.env` file in the frontend root with:

```properties
VITE_API_BASE_URL=http://localhost:8000/api
VITE_APP_NAME=MockProject
```

Note: Only variables prefixed with `VITE_` are exposed to the client-side code.

## Scripts

- `npm run dev` - Start development server
- `npm run build` - Build for production
- `npm run preview` - Preview production build
- `npm run lint` - Run ESLint (if configured)

## Troubleshooting

### CORS Issues
If you get CORS errors, make sure:
1. Backend is running on `http://localhost:8000`
2. Backend has CORS middleware configured
3. `VITE_API_BASE_URL` in `.env` is correct

### Port Already in Use
If port 5173 is already in use, Vite will automatically use the next available port.

## Additional Resources

- [Vite Guide](https://vitejs.dev/guide/)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Axios Documentation](https://axios-http.com/docs/intro)
