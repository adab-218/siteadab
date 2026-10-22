import React from 'react';

interface FieldProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label: string;
  erro?: string;
}

/**
 * Campo de formulário com <label htmlFor> + mensagem de erro
 * associada via aria-describedby. O id é obrigatório justamente
 * para forçar a associação label↔input.
 */
const Field: React.FC<FieldProps> = ({ label, erro, id, ...inputProps }) => {
  const erroId = `${id}-erro`;
  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-gray-700 mb-1">
        {label}
        {inputProps.required && <span className="text-red-600" aria-hidden="true"> *</span>}
      </label>
      <input
        id={id}
        aria-invalid={erro ? true : undefined}
        aria-describedby={erro ? erroId : undefined}
        className="w-full border border-gray-300 rounded-lg px-3 py-2
                   focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent
                   disabled:bg-gray-100"
        {...inputProps}
      />
      {erro && (
        <p id={erroId} role="alert" className="mt-1 text-sm text-red-700">
          {erro}
        </p>
      )}
    </div>
  );
};

export default Field;
