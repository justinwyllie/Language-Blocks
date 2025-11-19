import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

import { useSelect } from '@wordpress/data';

import {  LinkPanel, AuthorPanel } from './components/components';
import { registerPlugin } from '@wordpress/plugins';
const { __ } = wp.i18n; //TODO check


import { GapFill } from './components/gapfill';
import { MultipleChoice } from './components/multiple-choice';


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
    category: 'widgets', /* custom https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/#managing-block-categories */ 
    //we are bypassing attributes as we are saving just one block of xml to post meta not html string
    attributes: {
        activityType: {
            type: 'string'
    
        },
		formData: {
            type: 'object',
            default: {}  // Just the data you care about
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
       
        
        //const forceUpdate = useForceUpdate();
     
        //this is run whenever a form field value is changed
        //edit = a functional component 
        //and useState goes here https://reactjs.org/docs/hooks-state.html
     
        const blockProps = useBlockProps();//? gets props passed to this 'edit' component?
        //TODO - does this make an ajax call or just get it from the data store
        
console.log("postType", postType);
        return (
            <div { ...blockProps }>
                <GapFill postType={postType} setAttributes={setAttributes} ></GapFill>
            </div>
        );
    },
 
  
    save() {
        return null;
    },
} );

 
/* https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/writing-your-first-block-type/ */
//https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/ 
//https://deliciousbrains.com/custom-gutenberg-block/
registerBlockType( 'activities/activity-multiple-choice', {
    apiVersion: 2,
    title: 'Activity Multiple Choice',
    icon: 'universal-access-alt',
    category: 'widgets', /* custom https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/#managing-block-categories */ 
    //we are bypassing attributes as we are saving just one block of xml to post meta not html string
    //TODO remove this
    attributes: {
        activityType: 'multiplechoice',
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
        
        
        //const forceUpdate = useForceUpdate();
     
        //this is run whenever a form field value is changed
        //edit = a functional component 
        //and useState goes here https://reactjs.org/docs/hooks-state.html
     
        const blockProps = useBlockProps();//? gets props passed to this 'edit' component?
        //TODO - does this make an ajax call or just get it from the data store
        
      

        return (
            <div { ...blockProps }>
                <MultipleChoice postType={postType}></MultipleChoice>
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