import { createBrowserRouter } from 'react-router-dom';
import { AppLayout } from '../layouts/AppLayout';
import { RouteErrorPage } from '../pages/RouteErrorPage';
import { lazyPage } from '../utils/lazyPage';
import { GuestRoute } from './GuestRoute';
import { ProtectedRoute } from './ProtectedRoute';
import { WaiterRoute } from './WaiterRoute';

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
const AccountNotificationsPage = lazyPage(() =>
  import('../pages/AccountNotificationsPage').then((module) => ({ default: module.AccountNotificationsPage })),
);
const DeliveryAddressesPage = lazyPage(() =>
  import('../pages/DeliveryAddressesPage').then((module) => ({ default: module.DeliveryAddressesPage })),
);
const ReferralPage = lazyPage(() => import('../pages/ReferralPage').then((module) => ({ default: module.ReferralPage })));
const RewardsPage = lazyPage(() => import('../pages/RewardsPage').then((module) => ({ default: module.RewardsPage })));
const LoyaltyPage = lazyPage(() => import('../pages/LoyaltyPage').then((module) => ({ default: module.LoyaltyPage })));
const DiningPage = lazyPage(() => import('../pages/DiningPage').then((module) => ({ default: module.DiningPage })));
const DiningSessionPage = lazyPage(() =>
  import('../pages/DiningSessionPage').then((module) => ({ default: module.DiningSessionPage })),
);
const DiningBillPage = lazyPage(() =>
  import('../pages/DiningSessionPage').then((module) => ({ default: module.DiningBillPage })),
);
const WaiterTablesPage = lazyPage(() =>
  import('../pages/waiter/WaiterTablesPage').then((module) => ({ default: module.WaiterTablesPage })),
);
const WaiterSessionPage = lazyPage(() =>
  import('../pages/waiter/WaiterSessionPage').then((module) => ({ default: module.WaiterSessionPage })),
);
const WaiterMenuPage = lazyPage(() =>
  import('../pages/waiter/WaiterMenuPage').then((module) => ({ default: module.WaiterMenuPage })),
);
const WaiterRoundReviewPage = lazyPage(() =>
  import('../pages/waiter/WaiterRoundReviewPage').then((module) => ({
    default: module.WaiterRoundReviewPage,
  })),
);
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
        element: <WaiterRoute />,
        children: [
          {
            path: 'waiter',
            element: <WaiterTablesPage />,
          },
          {
            path: 'waiter/sessions/:sessionId',
            element: <WaiterSessionPage />,
          },
          {
            path: 'waiter/sessions/:sessionId/menu',
            element: <WaiterMenuPage />,
          },
          {
            path: 'waiter/sessions/:sessionId/review',
            element: <WaiterRoundReviewPage />,
          },
        ],
      },
      {
        element: <ProtectedRoute />,
        children: [
          {
            path: 'checkout',
            element: <CheckoutPage />,
          },
          {
            path: 'dining',
            element: <DiningPage />,
          },
          {
            path: 'dining/sessions/:sessionId',
            element: <DiningSessionPage />,
          },
          {
            path: 'dining/sessions/:sessionId/bill',
            element: <DiningBillPage />,
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
          {
            path: 'account/notifications',
            element: <AccountNotificationsPage />,
          },
          {
            path: 'account/delivery-addresses',
            element: <DeliveryAddressesPage />,
          },
          {
            path: 'account/referral',
            element: <ReferralPage />,
          },
          {
            path: 'account/rewards',
            element: <RewardsPage />,
          },
          {
            path: 'account/loyalty',
            element: <LoyaltyPage />,
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
