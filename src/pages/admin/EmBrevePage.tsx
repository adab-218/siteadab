import React from 'react';
import { useParams } from 'react-router-dom';
import { Construction } from 'lucide-react';

const EmBrevePage: React.FC = () => {
  const { modulo } = useParams();

  return (
    <div className="flex flex-col items-center justify-center py-24 text-center">
      <Construction size={40} className="text-red-600 mb-4" />
      <h1 className="text-xl font-bold text-gray-800 capitalize">{modulo}</h1>
      <p className="text-gray-500 mt-2">Este módulo está em construção.</p>
    </div>
  );
};

export default EmBrevePage;
