import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Panel, PanelBody, PanelRow  } from '@wordpress/components';
import { DataViews } from '@wordpress/dataviews';
import '../dataview.css';
import { ExternalLink } from '@wordpress/components';
import ManageTranscription from './ManageTranscriptions/ManageTranscription';

const ConfirmDelete = ({ fileName, onConfirm, onCancel, closeModal }) => {
    return (
        <div>
            <p>Sei sicuro di voler cancellare la trascrizione {fileName}?</p>
            <button onClick={() => onConfirm(fileName, closeModal)}>Sì</button>
            <button onClick={() => onCancel(closeModal)}>No</button>
        </div>
    );
};

const paginateArray = (array, page, perPage) => {
    const startIndex = (page - 1) * perPage;
    const endIndex = startIndex + perPage;
    return array.slice(startIndex, endIndex);
};

const TranscriptionsDataView = ( { transcriptions, onSavingTranscription, onDeletingTranscription }) => {

    const [ view, setView ] = useState( {
        type: 'table',
        perPage: 5,
        page: 1,
        sort: {},
        search: '',
        // filters: [
        //     { field: 'author', operator: 'is', value: 2 },
        //     { field: 'status', operator: 'isAny', value: [ 'publish', 'draft' ] }
        // ],
        hiddenFields: [],
        layout: {},
    } );

    console.log('rendering transcriptions:', transcriptions);

    const [ selectedTranscription, setSelectedTranscription ] = useState(null);
    const [ paginatedTranscriptions, setPaginatedTranscriptions ] = useState(paginateArray(transcriptions, view.page, view.perPage));

    useEffect(() => {
        console.log('TranscriptionsDataView useEffect');
        setPaginatedTranscriptions(paginateArray([...transcriptions], view.page, view.perPage));
    }, [transcriptions]);

    const paginationInfo = {
        totalItems: transcriptions.length,
        totalPages: Math.ceil(transcriptions.length / view.perPage)
    }
    
    const handleConfirmDelete = async (file_id, closeModal) => {
        // const updatedTranscriptions = transcriptions.filter((transcription) => transcription.file_id !== fileName);
        // setPaginatedTranscriptions(paginateArray(updatedTranscriptions, view.page, view.perPage));
        onDeletingTranscription(file_id);
        closeModal();
    };

    const handleCancelDelete = (closeModal) => {
        //setFileNameToDelete(null);
        closeModal();
    };

    const handleSaveTranscription = (transcription) => {
        setSelectedTranscription(transcription);
        onSavingTranscription(transcription);
    };

    const actions=[
        {
          RenderModal: (item) => { 
            console.log('item:', item);
            return <ConfirmDelete fileName={item.items[0].file_id} onConfirm={handleConfirmDelete} onCancel={handleCancelDelete} closeModal={item.closeModal} /> 
          },
          hideModalHeader: true,
          icon: <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path clipRule="evenodd" d="M12 5.5A2.25 2.25 0 0 0 9.878 7h4.244A2.251 2.251 0 0 0 12 5.5ZM12 4a3.751 3.751 0 0 0-3.675 3H5v1.5h1.27l.818 8.997a2.75 2.75 0 0 0 2.739 2.501h4.347a2.75 2.75 0 0 0 2.738-2.5L17.73 8.5H19V7h-3.325A3.751 3.751 0 0 0 12 4Zm4.224 4.5H7.776l.806 8.861a1.25 1.25 0 0 0 1.245 1.137h4.347a1.25 1.25 0 0 0 1.245-1.137l.805-8.861Z" fillRule="evenodd"/></svg>,
          id: 'delete',
          isPrimary: true,
          label: 'Delete item'
        },
        {
            icon: <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="100" height="100" viewBox="0 0 16 16">
            <path fill="#f5ce85" d="M2.382 11.428L3.577 10.234 5.923 12.277 4.583 13.618 1.382 14.618z"></path><path fill="#967a44" d="M3.589,10.576l1.968,1.714l-1.107,1.107l-2.686,0.839l0.839-2.675L3.589,10.576 M3.565,9.892 l-1.403,1.403L1,14.999l3.716-1.161l1.573-1.573L3.565,9.892L3.565,9.892z"></path><path fill="#36404d" d="M1.806 12.436L1.001 15 3.573 14.196z"></path><path fill="#f78f8f" d="M11.062,2.743l1.039-1.04c0.293-0.293,0.684-0.455,1.098-0.455s0.805,0.162,1.098,0.455 c0.604,0.605,0.604,1.589,0,2.194l-1.04,1.04L11.062,2.743z"></path><path fill="#c74343" d="M13.198,1.499c0.348,0,0.675,0.135,0.921,0.381c0.508,0.508,0.508,1.333,0,1.841l-0.863,0.863 l-1.841-1.841l0.863-0.863C12.524,1.634,12.85,1.499,13.198,1.499 M13.198,0.999c-0.461,0-0.922,0.176-1.274,0.528l-1.216,1.216 l2.548,2.548l1.216-1.216c0.704-0.704,0.704-1.845,0-2.548C14.12,1.175,13.659,0.999,13.198,0.999L13.198,0.999z"></path><g><path fill="#ffeea3" d="M4.883,13.317c-0.03-0.066-0.071-0.133-0.125-0.196c-0.146-0.171-0.352-0.276-0.613-0.314 c-0.099-0.589-0.565-0.842-0.934-0.921c-0.064-0.395-0.298-0.619-0.552-0.734l8.549-8.555l2.195,2.195L4.883,13.317z"></path><path fill="#ba9b48" d="M11.208,2.95l1.841,1.841l-8.13,8.136c-0.116-0.125-0.296-0.259-0.568-0.332 c-0.149-0.5-0.543-0.786-0.931-0.911c-0.07-0.242-0.201-0.432-0.367-0.574L11.208,2.95 M11.208,2.243l-9.046,9.052 c0,0,0.003,0,0.01,0c0.093,0,0.805,0.026,0.805,0.812c0,0,0.936,0.029,0.936,0.934c0.93,0,0.803,0.797,0.803,0.797l9.041-9.047 L11.208,2.243L11.208,2.243z"></path></g><g><path fill="#d9e7f5" d="M10.238 2.699H13.258V5.803H10.238z" transform="rotate(-45.009 11.747 4.252)"></path><path fill="#788b9c" d="M11.718,2.44l1.841,1.841l-1.782,1.782L9.936,4.222L11.718,2.44 M11.718,1.733L9.229,4.222 l2.548,2.548l2.489-2.489L11.718,1.733L11.718,1.733z"></path></g>
            </svg>,
            id: 'edit',
            isPrimary: true,
            label: 'Edit item',
            callback: (item) => {
                console.log('edit item:', item);
                setSelectedTranscription(item[0]);
            }

        }
    ];

    const fields = [
        {
            id: 'videoTitle',
            header: 'Nome',
            enableHiding: false,
            render: ( { item } ) => {
                if(item.transcription !== null) {
                    return (
                        <div>{ item.transcription.videoTitle }</div>
                    );
                }
                else {
                    return (
                        <div>Non disponibile</div>
                    );
                }
            }
        },
        {
            id: 'videoHrefLink',
            header: 'Link',
            render: ( { item } ) => {
                if(item.transcription !== null) {
                    return (
                        <ExternalLink href={item.transcription.videoHrefLink} target="_blank">
                            {item.transcription.videoHrefLink}
                        </ExternalLink>
                    );
                } else {
                    return (
                        <div>Non disponibile</div>
                    );
                }
            }
        },
        {
            id: 'assistant',
            header: 'Assistente',
            render: ( { item } ) => {
                if(item.assistant_id !== null && item.assistant_name !== null) {
                    return (
                        <div>{ item.assistant_name }</div>
                    );
                }
                else {
                    return (
                        <div>Non assegnato</div>
                    );
                }
               
            }
        },
    ] 

    const onViewChanged = (view) => {
        console.log('view changed', view);
        if(transcriptions.length > 0) {
            setPaginatedTranscriptions(paginateArray(transcriptions, view.page, view.perPage));
        }
        setView(view);
    };


    const geItemId = (item) => {
        return item.file_id;
    }

    return (
        <>  
            <h2>Lista delle tracrizioni</h2>
            <DataViews
                data={ paginatedTranscriptions }
                fields={ fields }
                view={ view }
                onChangeView={ onViewChanged }
                paginationInfo={ paginationInfo }
                getItemId={ geItemId }
                actions = {actions}
            />
            <br />
            {selectedTranscription && (
                <>
                    <Panel>
                        <PanelBody>
                            <ManageTranscription 
                                selectedTranscription={selectedTranscription} 
                                onTranscriptionSave={handleSaveTranscription}
                            />
                        </PanelBody>
                    </Panel>
                    <br />
                </>
            )}
        </>
    );
};

export default TranscriptionsDataView;