import { createBrowserRouter } from 'react-router-dom';
import { AppLayout } from '../layouts/AppLayout';
import { RouteErrorPage } from '../pages/RouteErrorPage';
import { lazyPage } from '../utils/lazyPage';
import { GuestRoute } from './GuestRoute';
import { ProtectedRoute } from './ProtectedRoute';

const HomePage = lazyPage(() => import('../pages/HomePage').then((module) => ({ default: module.HomePage })));
const MenuPage = lazyPage(() => import('../pages/MenuPage').then((module) => ({ default: module.MenuPage })));
const ProductDetailPage = lazyPage(() =>
  import('../pages/ProductDetailPage').then((module) => ({ default: module.ProductDetailPage })),
);
const AboutPage = lazyPage(() => import('../pages/AboutPage').then((module) => ({ default: module.AboutPage })));
const ContactPage = lazyPage(() => import('../pages/ContactPage').then((module) => ({ default: module.ContactPage })));
const FaqPage = lazyPage(() => import('../pages/FaqPage').then((module) => ({ default: module.FaqPage })));
const TermsPage = lazyPage(() => import('../pages/TermsPage').then((module) => ({ default: module.TermsPage })));
const PrivacyPage = lazyPage(() => import('../pages/PrivacyPage').then((module) => ({ default: module.PrivacyPage })));
const LoginPage = lazyPage(() => import('../pages/LoginPage').then((module) => ({ default: module.LoginPage })));
const RegisterPage = lazyPage(() => import('../pages/RegisterPage').then((module) => ({ default: module.RegisterPage })));
const ForgotPasswordPage = lazyPage(() =>
  import('../pages/ForgotPasswordPage').then((module) => ({ default: module.ForgotPasswordPage })),
);
const ResetPasswordPage = lazyPage(() =>
  import('../pages/ResetPasswordPage').then((module) => ({ default: module.ResetPasswordPage })),
);
const CartPage = lazyPage(() => import('../pages/CartPage').then((module) => ({ default: module.CartPage })));
const CheckoutPage = lazyPage(() => import('../pages/CheckoutPage').then((module) => ({ default: module.CheckoutPage })));
const FavouritesPage = lazyPage(() =>
  import('../pages/FavouritesPage').then((module) => ({ default: module.FavouritesPage })),
);
const OrdersPage = lazyPage(() => import('../pages/OrdersPage').then((module) => ({ default: module.OrdersPage })));
const OrderConfirmationPage = lazyPage(() =>
  import('../pages/OrderConfirmationPage').then((module) => ({ default: module.OrderConfirmationPage })),
);
const OrderDetailPage = lazyPage(() =>
  import('../pages/OrderDetailPage').then((module) => ({ default: module.OrderDetailPage })),
);
const AccountPage = lazyPage(() => import('../pages/AccountPage').then((module) => ({ default: module.AccountPage })));
const NotFoundPage = lazyPage(() => import('../pages/NotFoundPage').then((module) => ({ default: module.NotFoundPage })));

export const router = createBrowserRouter([
  {
    path: '/',
    element: <AppLayout />,
    errorElement: <RouteErrorPage />,
    children: [
      {
        index: true,
        element: <HomePage />,
      },
      {
        path: 'menu',
        element: <MenuPage />,
      },
      {
        path: 'menu/:productId',
        element: <ProductDetailPage />,
      },
      {
        path: 'about',
        element: <AboutPage />,
      },
      {
        path: 'contact',
        element: <ContactPage />,
      },
      {
        path: 'faq',
        element: <FaqPage />,
      },
      {
        path: 'terms',
        element: <TermsPage />,
      },
      {
        path: 'privacy',
        element: <PrivacyPage />,
      },
      {
        element: <GuestRoute />,
        children: [
          {
            path: 'login',
            element: <LoginPage />,
          },
          {
            path: 'register',
            element: <RegisterPage />,
          },
          {
            path: 'forgot-password',
            element: <ForgotPasswordPage />,
          },
          {
            path: 'reset-password',
            element: <ResetPasswordPage />,
          },
        ],
      },
      {
        path: 'cart',
        element: <CartPage />,
      },
      {
        element: <ProtectedRoute />,
        children: [
          {
            path: 'checkout',
            element: <CheckoutPage />,
          },
          {
            path: 'favourites',
            element: <FavouritesPage />,
          },
          {
            path: 'orders',
            element: <OrdersPage />,
          },
          {
            path: 'orders/:orderId/confirmation',
            element: <OrderConfirmationPage />,
          },
          {
            path: 'orders/:orderId',
            element: <OrderDetailPage />,
          },
          {
            path: 'account',
            element: <AccountPage />,
          },
        ],
      },
      {
        path: '*',
        element: <NotFoundPage />,
      },
    ],
  },
]);
