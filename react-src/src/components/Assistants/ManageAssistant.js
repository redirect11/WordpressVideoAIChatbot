import React, { useState, useEffect } from 'react';
import { useDispatch } from 'react-redux';
import { useDispatch as useDispatchWordpress } from '@wordpress/data';
import { useSelect } from '@wordpress/data';
import { setAssistants } from '../../redux/slices/AssistantsSlice';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { Button, 
         TextControl, 
         TextareaControl, 
         PanelRow,
         PanelBody,
         NoticeList } from '@wordpress/components';


const ManageAssistant = ({assistant}) => {
    const [name, setName] = useState('');
    const [prompt, setPrompt] = useState('');
    const [title, setTitle] = useState(__( 'Crea Assistente', 'video-ai-chatbot' ));
    const [initialOpen, setInitialOpen] = useState(false);
    const dispatch = useDispatch();

    useEffect(() => {
        if (assistant) {
            setName(assistant.name);
            setPrompt(assistant.instructions);
            setTitle(__( 'Modifica Assistente', 'video-ai-chatbot' ));	
            setInitialOpen(true);
        }
    }, [assistant]);

    const { createSuccessNotice, createErrorNotice } = useDispatchWordpress( noticesStore );

    const Notices = () => {
        const { removeNotice } = useDispatchWordpress( noticesStore );
        const notices = useSelect( ( select ) =>
            select( noticesStore ).getNotices()
        );
    
        if ( notices.length === 0 ) {
            return null;
        }
    
        return <NoticeList notices={ notices } onRemove={ removeNotice } />;
    };

    const handleCreateAssistant = async () => {

        const response = await fetch('/wp-json/video-ai-chatbot/v1/' + (assistant ? 'update-assistant/' : 'create-assistant/'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ name, prompt }),
        });

        if (response.ok) {
            createSuccessNotice(
                __( 'Settings saved.', 'video-ai-chatbot' )
            );
            // Aggiorna la lista degli assistenti
            const data = await response.json();
            dispatch(setAssistants(data.assistants));
            //setTimeout(() => window.location.reload(), 2000);
        } else {
            createErrorNotice( __('Errore nella creazione dell\'assistente.', 'video-ai-chatbot'));
        }
    };

    return (
        <PanelBody                    
            title={ title }
            initialOpen={ initialOpen }>
            <PanelRow>
                <div>
                    <TextControl
                        label="Nome"
                        value={  name }
                        onChange={ (value) => setName(value) }
                    />
                    <br />
                    <TextareaControl
                        label="Prompt"
                        help="Inserisci le istruzioni per l'assistente."
                        value={ prompt }
                        onChange={ (value) => setPrompt(value) }
                    />
                    <Button variant="primary" onClick={handleCreateAssistant}>Salva</Button>
                </div>
                {/* {message && <p>{message}</p>} */}
            </PanelRow>
            <Notices />
        </PanelBody>
    );
};

export default ManageAssistant;
