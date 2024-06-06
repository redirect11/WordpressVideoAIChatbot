import React, { useEffect, useState } from 'react';
import { CheckboxControl } from '@wordpress/components';
import './AssistantsGrid.css';

const AssistantsGrid = ({transcription, onTranscriptionUpdated}) => {
    const [assistants, setAssistants] = useState([]);
    const [isChecked, setIsChecked] = useState([]);

    console.log('AssistantGrid');

    useEffect(() => {
        console.log('transcription changed', transcription);
        if (transcription.assistant_id && assistants.length > 0) {
            setIsChecked(assistants.map((assistant) => transcription.assistant_id.includes(assistant.id)));
        } else if(assistants.length > 0) {
            setIsChecked(assistants.map(() => false));
        }
    }, [transcription, assistants]);

    const handleCheck = (checked, assistand_id) => {
        console.log('assistant', assistand_id);
        console.log('checked', checked);
        let tempTranscription = { ...transcription };
        if(checked && transcription.assistant_id) {
            tempTranscription = { ...transcription, assistant_id: [...transcription.assistant_id, assistand_id ]};
        } else if(checked) {	
            tempTranscription = { ...transcription, assistant_id: [assistand_id] };
        } else if(transcription.assistant_id){
            tempTranscription = { ...transcription, assistant_id: transcription.assistant_id.filter((el) => el.id !== assistand_id)};
        }
        const updatedTranscription = { ...tempTranscription };
        console.log('updatedTranscription', updatedTranscription);
        onTranscriptionUpdated(updatedTranscription);
    }

    const check = (assistand_id, index) => (e) => {
        handleCheck(e, assistand_id);
        setIsChecked((isChecked) =>
          isChecked.map((el, i) => (i === index ? e : el))
        );
      };


    useEffect(() => {
        console.log('fetching assistants');
        fetch('/wp-json/video-ai-chatbot/v1/assistants')
            .then(response => response.json())
            .then(data => {
                console.log(data.data);
                setAssistants(data.data);
            });
    }, []);


    return (
        <div className='assistants-list'>
            {assistants && assistants.map((assistant, i) => (
                <CheckboxControl 
                    key={assistant.id}
                    className='assistant' 
                    label={assistant.name} 
                    checked={ isChecked[i] }
                    onChange={check(assistant.id, i)} // Remove the extra comma after the closing parenthesis
                />
            ))}
        </div>
    );
}

export default AssistantsGrid;