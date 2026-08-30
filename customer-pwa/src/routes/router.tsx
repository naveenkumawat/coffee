import { createBrowserRouter } from 'react-router-dom';
import { AppLayout } from '../layouts/AppLayout';
import { AccountPage } from '../pages/AccountPage';
import { CartPage } from '../pages/CartPage';
import { ForgotPasswordPage } from '../pages/ForgotPasswordPage';
import { HomePage } from '../pages/HomePage';
import { LoginPage } from '../pages/LoginPage';
import { MenuPage } from '../pages/MenuPage';
import { NotFoundPage } from '../pages/NotFoundPage';
import { OrderConfirmationPage } from '../pages/OrderConfirmationPage';
import { OrdersPage } from '../pages/OrdersPage';
import { CheckoutPage } from '../pages/CheckoutPage';
import { ProductDetailPage } from '../pages/ProductDetailPage';
import { RegisterPage } from '../pages/RegisterPage';
import { ResetPasswordPage } from '../pages/ResetPasswordPage';
import { GuestRoute } from './GuestRoute';
import { ProtectedRoute } from './ProtectedRoute';

export const router = createBrowserRouter([
  {
    path: '/',
    element: <AppLayout />,
    children: [
      {
        index: true,
        element: <HomePage />
      },
      {
        path: 'menu',
        element: <MenuPage />
      },
      {
        path: 'menu/:productId',
        element: <ProductDetailPage />
      },
      {
        element: <GuestRoute />,
        children: [
          {
            path: 'login',
            element: <LoginPage />
          },
          {
            path: 'register',
            element: <RegisterPage />
          },
          {
            path: 'forgot-password',
            element: <ForgotPasswordPage />
          },
          {
            path: 'reset-password',
            element: <ResetPasswordPage />
          }
        ]
      },
      {
        element: <ProtectedRoute />,
        children: [
          {
            path: 'cart',
            element: <CartPage />
          },
          {
            path: 'checkout',
            element: <CheckoutPage />
          },
          {
            path: 'orders',
            element: <OrdersPage />
          },
          {
            path: 'orders/:orderId/confirmation',
            element: <OrderConfirmationPage />
          },
          {
            path: 'account',
            element: <AccountPage />
          }
        ]
      },
      {
        path: '*',
        element: <NotFoundPage />
      }
    ]
  }
]);
