import React from 'react';
import Loader from './components/Loader';

import { createCustomMessage } from "react-chatbot-kit";

const ActionProvider = ({ createChatBotMessage, setState, state, children }) => {


  const handleUserMessage = (message) => {
    console.log('message:', message);

    console.log('state:');
    console.log(state);

    const loading = createChatBotMessage(<Loader />)
    setState((prev) => ({ ...prev, messages: [...prev.messages, loading], }))

    fetch('/wp-json/video-ai-chatbot/v1/chatbot/', {
      method: 'POST',
      headers: {
          'Content-Type': 'application/json',
      },
      body: JSON.stringify({ message: message,
                             assistant_id: state.selectedAssistant})
    })
    .then(response => { return response.json()})
    .then((data) => {
        const botMessage = createCustomMessage(data.message, "customWithLinks", {payload: data.message});

        setState((prev) => {
          const newPrevMsg = prev.messages.slice(0, -1)
          return {
            ...prev,
            messages: [...newPrevMsg, botMessage],
          }
        });
    });
  };

  const handleAssistantChoice = (selectedAssistant) => {
    console.log('assistant choice:', selectedAssistant);

    setState((prev) => ({
      ...prev,
      selectedAssistant: selectedAssistant.id,
    }));

    const loading = createChatBotMessage(<Loader />)
    setState((prev) => ({ ...prev, messages: [...prev.messages, loading], }))

    fetch('/wp-json/video-ai-chatbot/v1/chatbot/', {
      method: 'POST',
      headers: {
          'Content-Type': 'application/json',
      },
      body: JSON.stringify({ message: 'Ciao!', //TODO remove hardocoded message
                             assistant_id: selectedAssistant.id})
    })
    .then(response => { return response.json()})
    .then((data) => {
        const botMessage = createCustomMessage(data.message, "customWithLinks", {payload: data.message});
        setState((prev) => {
          const newPrevMsg = prev.messages.slice(0, -1)
          return {
            ...prev,
            messages: [...newPrevMsg, botMessage],
          }
        });
    });

  }

  return (
    <div>
      {React.Children.map(children, (child) => {
        return React.cloneElement(child, {
          actions: {
            handleAssistantChoice,
            handleUserMessage
          },
        });
      })}
    </div>
  );
};

export default ActionProvider;