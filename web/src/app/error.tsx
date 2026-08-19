import * as Sentry from "@sentry/nextjs";
import * as React from "react";

export class ErrorBoundary extends React.Component<
  React.ComponentProps<"div">,
  { hasError: boolean; error?: Error; errorInfo?: React.ErrorInfo }
> {
  static getDerivedStateFromError(error: Error) {
    return { hasError: true };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    Sentry.captureException(error);
  }

  render() {
    if (this.state.hasError) {
      return <div className="p-6 bg-red-100 text-red-800 rounded-md">Error boundary caught an error. See logs.</div>;
    }
    return this.props.children;
  }
}