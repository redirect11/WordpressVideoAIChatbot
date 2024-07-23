import React from 'react';
import Loader from './components/Loader';

import { createCustomMessage } from "react-chatbot-kit";

const ActionProvider = ({ createChatBotMessage, setState, state, children }) => {


  const handleData = (data) => {
    console.log('data:', data);
    let dataMessage = data.message.value;
    const widgetType = !data.message.value ? "customWithError" : "customWithLinks";
    if(!dataMessage) {
      dataMessage = "I'm sorry, I don't have an answer for that. Please try again.";
    }
    const pattern = /【.*?†source】/g;
    // Replace the pattern with an empty string
    dataMessage = dataMessage.replace(pattern, '');
    const botMessage = createCustomMessage(dataMessage, widgetType, {payload: dataMessage});
    console.log('botMessage:', botMessage);

    setState((prev) => {
      const newPrevMsg = prev.messages.slice(0, -1)
      return {
        ...prev,
        messages: [...newPrevMsg, botMessage],
      }
    });
  }

  const handleUserMessage = (message) => {

    console.log(state);

    const loading = createCustomMessage("" , "loaderMessage")
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
    .then(handleData);
  };

  const handleAssistantChoice = (selectedAssistant) => {
    console.log('assistant choice:', selectedAssistant);

    setState((prev) => ({
      ...prev,
      selectedAssistant: selectedAssistant.id,
    }));

    const loading = createCustomMessage("", "loaderMessage")
    setState((prev) => ({ ...prev, messages: [...prev.messages, loading], }))

    let message = ''; //TODO remove hardocoded message

    fetch('/wp-json/video-ai-chatbot/v1/chatbot/', {
      method: 'POST',
      headers: {
          'Content-Type': 'application/json',
      },
      body: JSON.stringify({ message: message, //TODO remove hardocoded message
                             assistant_id: selectedAssistant.id,
                             postprompt: "true"})
    })
    .then(response => { return response.json()})
    .then(handleData);
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