import React, { useEffect, useState } from 'react';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { useSelector, useDispatch } from 'react-redux';
import { setAssistants, setSelectedAssistant } from './redux/slices/AssistantsSlice';
import store from './redux/store';
import { Provider } from 'react-redux';
import ManageAssistant from './components/Assistants/ManageAssistant';
import { __ } from '@wordpress/i18n';
import { Panel, PanelBody, PanelRow } from '@wordpress/components';
import { DataViews } from '@wordpress/dataviews';
import moment from 'moment';
import './components/dataview.css';

// const registerCustomEntities = () => {
//     const {getEntitiesByKind} = select('core');
//     const namespace = 'video-ai-chatbot/v1';

//     if (getEntitiesByKind(namespace).length === 0) {
//         dispatch('core').addEntities([
//         {
//             label: 'Assistants',
//             name: 'assistants',
//             kind: namespace,
//             baseURL: namespace + '/assistants'
//         }
//         ]);
//     }
// };


const AssistantsPage = () => {
    const fields = [
        {
            id: 'name',
            header: 'Nome',
            enableHiding: false,
            render: ( { item } ) => {
                return (
                    <div onClick={() => handleAssistantClick(item)}>{ item.name }</div>
                );
            },
        },
        {
            id: 'created_at',
            header: 'Data di creazione',
            render: ( { item } ) => {
                var dateString = moment.unix( item.created_at ).format("hh:mm:ss MM/DD/YYYY");
                return (
                    <time>{ dateString }</time>
                );
            }
        },
    ] 
    
    // const view = {
    //     type: 'table',
    //     search: '',
    //     filters: [
    //         { field: 'name', operator: 'is', value: 2 },
    //     ],
    //     page: 1,
    //     perPage: 5,
    //     sort: {
    //         field: 'date',
    //         direction: 'desc',
    //     },
    //     hiddenFields: [ 'date' ],
    //     layout: {},
    // }
    
    const paginationInfo = {
        totalItems: 11,
        totalPages: 2
    }

    const [ view, setView ] = useState( {
        type: 'table',
        perPage: 5,
        page: 1,
        sort: {
            field: 'date',
            direction: 'desc',
        },
        search: '',
        // filters: [
        //     { field: 'author', operator: 'is', value: 2 },
        //     { field: 'status', operator: 'isAny', value: [ 'publish', 'draft' ] }
        // ],
        hiddenFields: [ 'date' ],
        layout: {},
    } );

    const dispatch = useDispatch();
    const assistants = window.adminData.assistants ? window.adminData.assistants.data.data : []; //todo change the json structure
    //console.log(state)
    const selectedAssistant = useSelector((state) => state.rootReducer.assistants.selectedAssistant);
    console.log('assistants:', assistants);

    useEffect(() => {
        dispatch(setAssistants(assistants));
    }, [dispatch]);

    const handleAssistantClick = (assistant) => {
        dispatch(setSelectedAssistant(assistant));
    }

    return (
        <>
            <Panel>
                <PanelBody>
                <div>
                    <h2>Lista degli Assistenti</h2>
                    <DataViews
                        data={ assistants }
                        fields={ fields }
                        view={ view }
                        onChangeView={ setView }
                        // actions={ actions }
                        paginationInfo={ paginationInfo }
                    />
                </div>
                </PanelBody>
            </Panel>
            <br />
            {selectedAssistant && (
                <>
                    <Panel>
                        <ManageAssistant assistant={selectedAssistant} />
                    </Panel>
                    <br />
                </>
            )}
            <Panel>
                <ManageAssistant assistant="" />
                <PanelBody
                    title={ __( 'Playground', 'unadorned-announcement-bar' ) }
                    initialOpen={ false }
                >
                    <PanelRow>
                        <h2>Assistenti</h2>
                        <button id="create-new-assistant" class="button button-primary" >Apri playground</button>
                    </PanelRow>
                </PanelBody>
            </Panel>
        </>
    );
};

domReady( () => {
    const root = createRoot(document.getElementById('react-assistants-page'))
    root.render(
        <Provider store={store}>
             <AssistantsPage />
        </Provider>
    );
} );