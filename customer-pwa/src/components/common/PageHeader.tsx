import { ReactNode } from 'react';
import { Link, useNavigate } from 'react-router-dom';

interface PageHeaderProps {
  title: string;
  description?: string;
  showBack?: boolean;
  rightSlot?: ReactNode;
}

export function PageHeader({ title, description, showBack = false, rightSlot }: PageHeaderProps) {
  const navigate = useNavigate();

  return (
    <header className="page-header">
      <div className="page-header-main">
        <div className="page-header-leading">
          {showBack ? (
            <button type="button" className="icon-button" onClick={() => navigate(-1)} aria-label="Go back">
              <i className="bi bi-arrow-left"></i>
            </button>
          ) : (
            <Link to="/" className="icon-button" aria-label="Go home">
              <i className="bi bi-house-door"></i>
            </Link>
          )}
          <div>
            <h1>{title}</h1>
            {description ? <p>{description}</p> : null}
          </div>
        </div>
        {rightSlot ? <div>{rightSlot}</div> : null}
      </div>
    </header>
  );
}
