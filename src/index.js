import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import {  LinkPanel, AuthorPanel} from './components/components';
import { registerPlugin } from '@wordpress/plugins';
const { __ } = wp.i18n; //TODO check


import { GapFill } from './components/gapfill';


//import bootstrap CSS 
//rather lucily the webpack installed by wp-scripts includes a process for scss
//and by default builds it to index.css minified. 
import './custom.scss';


/**
 * 
 *  
 * https://formik.org/docs/examples/field-arrays
 * rendering problem: 
 * https://wordpress.org/support/topic/gutenberg-block-constantly-re-renders-on-state-change-in-inner-component/
 */

/**
 * react docs: https://reactjs.org/docs/hooks-state.html state v hooks 
 * https://reactjs.org/docs/hooks-intro.html
 * https://tsh.io/blog/react-component-lifecycle-methods-vs-hooks/
 * https://dev.to/martinkr/create-a-wordpress-s-gutenberg-block-with-all-react-lifecycle-methods-in-5-minutes-213p
 * https://www.youtube.com/watch?v=6x7GXs6Foaw
 * 
 * 
 * TODO https://blog.logrocket.com/a-guide-to-usestate-in-react-ecb9952e406c/#usinganobjectasastatevariablewithusestatehook
 *  check how I am doing this
 */


/*
WIP

save data entered into the body of the post into
the meta field
+ remove the meta field displayed in the editor

https://developer.wordpress.org/block-editor/how-to-guides/metabox/meta-block-3-add/ - creating the block
with a text control 



TODO
fix sec issues in wp-scripts - run audit and maybe update version? try 16.0.0 not "^14.1.1"

*/  




//TODO - look up registerPlugin

registerPlugin( 'kea-grammar-author-meta', {
	render() {
		return(<AuthorPanel />);
	}
} );



registerPlugin( 'kea-grammar-links-meta', {
	render() {
		return(<LinkPanel />);
	}
} );


 





 
/* https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/writing-your-first-block-type/ */
//https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/ 
//https://deliciousbrains.com/custom-gutenberg-block/
registerBlockType( 'activities/activity-gap-fill', {
    apiVersion: 2,
    title: 'Activity Gap Fill',
    icon: 'universal-access-alt',
    category: 'design', /* custom https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/#managing-block-categories */ 
    //we are bypassing attributes as we are saving just one block of xml to post meta not html string
    attributes: {
		exampleText: {
			type: 'string',
            source: 'text',
			default: ''
		}
	
	},

    //https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/introducing-attributes-and-editable-fields/
    //how am i going to get the meta data?
    //https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/ see Meta (deprecated)
    //https://developer.wordpress.org/block-editor/how-to-guides/metabox/meta-block-1-intro/ 

   

    edit: ( { setAttributes, attributes } ) => {
        
        const postType = useSelect(
            ( select ) => {
                
                return select( 'core/editor' ).getCurrentPostType();
            },
            []
        );
        console.log("postType", postType);
        
        //const forceUpdate = useForceUpdate();
     
        //this is run whenever a form field value is changed
        //edit = a functional component 
        //and useState goes here https://reactjs.org/docs/hooks-state.html
     
        const blockProps = useBlockProps();//? gets props passed to this 'edit' component?
        //TODO - does this make an ajax call or just get it from the data store
        const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' ); 

        //validate form, if ok build XML (second validation step - test for valid
        //valid XML - call setMeta to update the meta field with the XML
        function processForm(values)
        {
            //?? how to manage form data: Formik
            //attributes ?
            
            
            //idea is values should contain current form values
            //we now process them into XML and save that to 
            //the post meta field _activity_gap_fill_meta
            

            //create the XML object.
            let parser = new DOMParser();
            let xml = '<?xml version="1.0" encoding="UTF-8"?><activity></activity>';
            let xmlDoc = parser.parseFromString(xml,"text/xml");

            let rootNode = xmlDoc.getElementsByTagName("activity")[0];
            rootNode.setAttribute("type", postType);
            //rootNode.setAttribute("ageGroup", values.ageGroup);
            //rootNode.setAttribute("level", values.level);

            let legacyNameNode = xmlDoc.createElement("legacyName");
            let legacyNameValueNode = xmlDoc.createTextNode(values.legacyName);
            legacyNameNode.appendChild(legacyNameValueNode);
            xmlDoc.getElementsByTagName("activity")[0].appendChild(legacyNameNode);

            let titleNode = xmlDoc.createElement("title");
            let titleValueNode = xmlDoc.createTextNode(values.title);
            titleNode.appendChild(titleValueNode);
            xmlDoc.getElementsByTagName("activity")[0].appendChild(titleNode);

            let modelsNode = xmlDoc.createElement("models");
            let modelsValueNode = xmlDoc.createTextNode(values.models);
            modelsNode.appendChild(modelsValueNode);
            xmlDoc.getElementsByTagName("activity")[0].appendChild(modelsNode);

            let explanationNode = xmlDoc.createElement("explanation");
            let explanationValueNode = xmlDoc.createTextNode(values.explanation);
            explanationNode.appendChild(explanationValueNode);
            xmlDoc.getElementsByTagName("activity")[0].appendChild(explanationNode);

            let instructionsNode = xmlDoc.createElement("instructions");
            values.instructions.forEach(function(item, i)
            {
                let iNode = xmlDoc.createElement("instruction");
                iNode.setAttribute("lang", item.lang);
                let iValueNode = xmlDoc.createTextNode(item.text);
                iNode.appendChild(iValueNode);
                instructionsNode.appendChild(iNode);
            });
            xmlDoc.getElementsByTagName("activity")[0].appendChild(instructionsNode);

            let questionsNode = xmlDoc.createElement("questions");
            values.questions.forEach(function(item, i)
            {
                let qNode = xmlDoc.createElement("q"+i);
                qNode.setAttribute("questionNumber", (i + 1));
                qNode.setAttribute("answer", item.answer);
                let qValueNode = xmlDoc.createTextNode(item.question);
                qNode.appendChild(qValueNode);
                questionsNode.appendChild(qNode);
            });
            xmlDoc.getElementsByTagName("activity")[0].appendChild(questionsNode);

            let s = new XMLSerializer();
            let newXmlStr = s.serializeToString(xmlDoc);
            values.rawXML = newXmlStr;
        

            //https://developer.wordpress.org/block-editor/how-to-guides/metabox/meta-block-3-add/ 
            //this seems to cause a re-render of the component. does it?
            //but does not save anything to the backend - that takes saving the whole post
            //via the button on the page?
            //console.log("setMeta");
            setMeta( { ...meta, _activity_gap_fill_meta: newXmlStr } );
            
        }
  
 
        return (
            <div { ...blockProps }>
                <GapFill postType={postType} metaData={meta} processForm={processForm}></GapFill>
            </div>
        );
    },
 
    // No information saved to the block
    // Data is saved to post meta via the hook
    //normally this would build the html using the set attributes? and return an html
    //string to save - which is what is displayed on the f/e
    save() {
        return null;
    },
} );