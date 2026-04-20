interface ErrorMessageProps {
  message: string;
}

export function ErrorMessage({ message }: ErrorMessageProps) {
  return (
    <p role="alert" className="text-red-600 text-sm mt-1">
      {message}
    </p>
  );
}
