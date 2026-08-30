import { lazy } from 'react';
import { createBrowserRouter } from 'react-router-dom';
import { AppLayout } from '../layouts/AppLayout';
import { GuestRoute } from './GuestRoute';
import { ProtectedRoute } from './ProtectedRoute';

const HomePage = lazy(() => import('../pages/HomePage').then((module) => ({ default: module.HomePage })));
const MenuPage = lazy(() => import('../pages/MenuPage').then((module) => ({ default: module.MenuPage })));
const ProductDetailPage = lazy(() =>
  import('../pages/ProductDetailPage').then((module) => ({ default: module.ProductDetailPage }))
);
const AboutPage = lazy(() => import('../pages/AboutPage').then((module) => ({ default: module.AboutPage })));
const ContactPage = lazy(() => import('../pages/ContactPage').then((module) => ({ default: module.ContactPage })));
const FaqPage = lazy(() => import('../pages/FaqPage').then((module) => ({ default: module.FaqPage })));
const TermsPage = lazy(() => import('../pages/TermsPage').then((module) => ({ default: module.TermsPage })));
const PrivacyPage = lazy(() => import('../pages/PrivacyPage').then((module) => ({ default: module.PrivacyPage })));
const LoginPage = lazy(() => import('../pages/LoginPage').then((module) => ({ default: module.LoginPage })));
const RegisterPage = lazy(() => import('../pages/RegisterPage').then((module) => ({ default: module.RegisterPage })));
const ForgotPasswordPage = lazy(() =>
  import('../pages/ForgotPasswordPage').then((module) => ({ default: module.ForgotPasswordPage }))
);
const ResetPasswordPage = lazy(() =>
  import('../pages/ResetPasswordPage').then((module) => ({ default: module.ResetPasswordPage }))
);
const CartPage = lazy(() => import('../pages/CartPage').then((module) => ({ default: module.CartPage })));
const CheckoutPage = lazy(() => import('../pages/CheckoutPage').then((module) => ({ default: module.CheckoutPage })));
const FavouritesPage = lazy(() =>
  import('../pages/FavouritesPage').then((module) => ({ default: module.FavouritesPage }))
);
const OrdersPage = lazy(() => import('../pages/OrdersPage').then((module) => ({ default: module.OrdersPage })));
const OrderConfirmationPage = lazy(() =>
  import('../pages/OrderConfirmationPage').then((module) => ({ default: module.OrderConfirmationPage }))
);
const OrderDetailPage = lazy(() =>
  import('../pages/OrderDetailPage').then((module) => ({ default: module.OrderDetailPage }))
);
const AccountPage = lazy(() => import('../pages/AccountPage').then((module) => ({ default: module.AccountPage })));
const NotFoundPage = lazy(() => import('../pages/NotFoundPage').then((module) => ({ default: module.NotFoundPage })));

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
        path: 'about',
        element: <AboutPage />
      },
      {
        path: 'contact',
        element: <ContactPage />
      },
      {
        path: 'faq',
        element: <FaqPage />
      },
      {
        path: 'terms',
        element: <TermsPage />
      },
      {
        path: 'privacy',
        element: <PrivacyPage />
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
            path: 'favourites',
            element: <FavouritesPage />
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
            path: 'orders/:orderId',
            element: <OrderDetailPage />
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
