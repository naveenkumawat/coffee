import { useEffect } from 'react';
import { Navigate, useParams } from 'react-router-dom';
import {
  writeOrderingContext,
} from '../utils/orderingContext';

/**
 * Deep-link alias: bind dining mode + session, then open the shared Menu.
 */
export function DiningMenuPage() {
  const { sessionId = '' } = useParams();

  useEffect(() => {
    if (!sessionId) {
      return;
    }

    writeOrderingContext({
      mode: 'dining',
      type: 'dining',
      diningSessionId: sessionId,
    });
  }, [sessionId]);

  return <Navigate to="/menu" replace />;
}
