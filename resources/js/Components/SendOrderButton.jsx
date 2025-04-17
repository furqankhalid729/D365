// components/SendOrderButton.jsx
import React, { useState } from 'react';

const SendOrderButton = ({ data }) => {
  const [loading, setLoading] = useState(false);
  const handleSend = async () => {
    try {
      setLoading(true);
      const res = await fetch(`/api/send-order/${data.id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });

      const result = await res.json();
      if (res.ok) {
        alert('Order sent successfully!');
        console.log(result);
      } else {
        alert('Failed to send order');
        console.error(result);
      }
    } catch (error) {
      alert('Error occurred while sending order.');
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <button onClick={handleSend} 
    disabled={loading}
    className={`px-4 py-1 rounded text-white text-sm font-medium transition 
        ${loading ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'}`}
    >
      {loading ? 'Sending...' : 'Send'}
    </button>
  );
};

export default SendOrderButton;
